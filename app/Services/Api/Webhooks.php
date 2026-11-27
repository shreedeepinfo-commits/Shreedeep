<?php

namespace App\Services\Api;

use App\Jobs\DeliverWebhook;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/** Sends platform events to the customer's own software. */
class Webhooks
{
    public static function dispatch(int $workspaceId, string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::withoutGlobalScopes()->where('workspace_id', $workspaceId)->where('active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => in_array($event, $e->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::withoutGlobalScopes()->create([
                'workspace_id' => $workspaceId, 'webhook_endpoint_id' => $endpoint->id, 'event' => $event,
                'payload' => ['event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data],
            ]);
            DeliverWebhook::dispatch($delivery->id);
        }
    }

    public static function signature(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }

    /** The public shape of a message in the API and in webhooks. */
    public static function message(Message $m): array
    {
        return [
            'id' => $m->id,
            'wamid' => $m->wamid,
            'direction' => $m->direction === 'in' ? 'inbound' : 'outbound',
            'from' => $m->direction === 'in' ? $m->phone : null,
            'to' => $m->direction === 'out' ? $m->phone : null,
            'type' => $m->type,
            'text' => $m->body,
            'status' => $m->status,
            'error' => $m->error_message,
            'campaign_id' => $m->campaign_id,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    public static function contact(Contact $c): array
    {
        return [
            'id' => $c->id, 'phone' => $c->phone, 'name' => $c->name, 'email' => $c->email,
            'tags' => $c->tags ?? [], 'fields' => (object) ($c->fields ?? []), 'lead_status' => $c->lead_status,
            'opted_out' => (bool) $c->opted_out_at, 'blocked' => (bool) $c->blocked_at,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
