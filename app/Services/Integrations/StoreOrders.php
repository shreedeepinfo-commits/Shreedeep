<?php

namespace App\Services\Integrations;

use App\Jobs\SendIntegrationTemplate;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\IntegrationEvent;
use App\Models\MessageTemplate;
use App\Models\Workspace;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Turns Shopify / WooCommerce order webhooks into contacts and WhatsApp order updates. */
class StoreOrders
{
    /** Both stores sign the raw body with HMAC-SHA256 and send it base64 encoded. */
    public static function verify(Integration $integration, Request $request): bool
    {
        $header = $integration->type === 'shopify' ? 'X-Shopify-Hmac-Sha256' : 'X-WC-Webhook-Signature';
        $given = (string) $request->header($header);
        if (! $integration->secret || $given === '') {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $integration->secret, true)), $given);
    }

    /**
     * @return null|array{event:string, external_id:string, order_number:string, phone:?string, name:?string, email:?string, fields:array<string,string>}
     */
    public static function parse(string $type, string $topic, array $o): ?array
    {
        if (empty($o['id'])) {
            return null;
        }

        if ($type === 'shopify') {
            $event = ['orders/create' => 'order_placed', 'orders/fulfilled' => 'order_shipped', 'orders/cancelled' => 'order_cancelled'][$topic] ?? null;
            $customer = $o['customer'] ?? [];
            $billing = $o['billing_address'] ?? [];
            $shipping = $o['shipping_address'] ?? [];
            $phone = $o['phone'] ?? $customer['phone'] ?? $shipping['phone'] ?? $billing['phone'] ?? null;
            $name = trim(($customer['first_name'] ?? '') ?: ($shipping['first_name'] ?? '') ?: ($billing['first_name'] ?? ''));
            $tracking = collect($o['fulfillments'] ?? [])->pluck('tracking_url')->filter()->last();
            $items = collect($o['line_items'] ?? [])->map(fn ($i) => ($i['title'] ?? 'Item').' x'.($i['quantity'] ?? 1));
            $number = (string) ($o['name'] ?? $o['order_number'] ?? $o['id']);
            $total = trim(($o['currency'] ?? '').' '.($o['total_price'] ?? ''));
            $url = $o['order_status_url'] ?? '';
            $email = $o['email'] ?? $customer['email'] ?? null;
        } else {
            $status = $o['status'] ?? '';
            $event = match (true) {
                $status === 'cancelled' && in_array($topic, ['order.updated', 'order.created'], true) => 'order_cancelled',
                $status === 'completed' && $topic === 'order.updated' => 'order_shipped',
                $topic === 'order.created' && ! in_array($status, ['failed', 'cancelled', 'trash'], true) => 'order_placed',
                default => null,
            };
            $billing = $o['billing'] ?? [];
            $shipping = $o['shipping'] ?? [];
            $phone = ($billing['phone'] ?? '') ?: ($shipping['phone'] ?? null);
            $name = trim(($billing['first_name'] ?? '') ?: ($shipping['first_name'] ?? ''));
            $items = collect($o['line_items'] ?? [])->map(fn ($i) => ($i['name'] ?? 'Item').' x'.($i['quantity'] ?? 1));
            $number = '#'.($o['number'] ?? $o['id']);
            $total = trim(($o['currency'] ?? '').' '.($o['total'] ?? ''));
            $url = '';
            $tracking = collect($o['meta_data'] ?? [])->firstWhere('key', '_tracking_url')['value'] ?? null;
            $email = $billing['email'] ?? null;
        }

        if (! $event) {
            return null;
        }

        return [
            'event' => $event,
            'external_id' => (string) $o['id'],
            'order_number' => Str::limit($number, 55, ''),
            'phone' => $phone ? (string) $phone : null,
            'name' => $name !== '' ? Str::limit($name, 100, '') : null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'fields' => [
                'order_number' => $number,
                'total' => $total,
                'items' => Str::limit($items->implode(', '), 900),
                'order_url' => (string) $url,
                'tracking_url' => (string) $tracking,
            ],
        ];
    }

    /** Saves the customer as a contact and queues the WhatsApp message set for this event. */
    public static function handle(Integration $integration, array $order): ?IntegrationEvent
    {
        $workspace = Workspace::find($integration->workspace_id);
        if (! $workspace) {
            return null;
        }

        try {
            $event = IntegrationEvent::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id, 'integration_id' => $integration->id, 'event' => $order['event'],
                'external_id' => $order['external_id'], 'order_number' => $order['order_number'], 'status' => 'queued',
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // the store retried a webhook we already handled
        }

        $skip = function (string $note) use ($event) {
            $event->update(['status' => 'skipped', 'note' => $note]);

            return $event;
        };

        if ($workspace->isSuspended() || $workspace->isExpired()) {
            return $skip('Your plan is not active.');
        }
        $phone = Phone::normalize($order['phone'], $integration->setting('country_code', '91'));
        if (! $phone) {
            return $skip('The order has no valid phone number.');
        }

        $contact = Contact::withoutGlobalScopes()->firstOrNew(['workspace_id' => $workspace->id, 'phone' => $phone]);
        if (! $contact->exists && ! $workspace->canAdd('contacts')) {
            return $skip('Contact limit of your plan reached.');
        }
        $label = Integration::STORES[$integration->type];
        if (! $contact->exists) {
            $contact->fill(['opted_in_at' => now(), 'opt_in_source' => $label]);
        }
        if (! $contact->name && $order['name']) {
            $contact->name = $order['name'];
        }
        if (! $contact->email && $order['email']) {
            $contact->email = $order['email'];
        }
        $contact->tags = array_values(array_unique(array_merge($contact->tags ?? [], [Str::lower($label).'-customer'])));
        $contact->fields = array_merge($contact->fields ?? [], [
            'last_order' => $order['order_number'], 'last_order_total' => $order['fields']['total'],
        ]);
        $contact->save();
        $event->update(['contact_id' => $contact->id]);

        $config = $integration->setting('events.'.$order['event'], []);
        $template = empty($config['enabled']) ? null : MessageTemplate::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)->where('status', 'APPROVED')->find($config['template_id'] ?? 0);
        if (! $template) {
            return $skip('Contact saved. No WhatsApp message is set for this event.');
        }
        if ($contact->opted_out_at || $contact->blocked_at) {
            return $skip('Customer has opted out of messages.');
        }

        $values = $order['fields'] + ['name' => $contact->name ?: 'there', 'store' => $integration->setting('store_name') ?: $workspace->name];
        $variables = ['body' => []];
        for ($i = 0; $i < $template->bodyVariableCount(); $i++) {
            $key = $config['vars'][$i] ?? 'name';
            $variables['body'][] = ['type' => 'text', 'value' => $values[$key] ?? '', 'fallback' => '-'];
        }
        if ($template->headerVariableCount() > 0) {
            $variables['header'] = ['type' => 'text', 'value' => $values[$config['header_var'] ?? 'store'] ?? '', 'fallback' => '-'];
        }

        SendIntegrationTemplate::dispatch($event->id, $contact->id, ['template_id' => $template->id, 'variables' => $variables]);

        return $event;
    }
}
