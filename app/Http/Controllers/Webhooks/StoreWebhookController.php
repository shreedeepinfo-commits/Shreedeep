<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStoreOrder;
use App\Models\Integration;
use App\Services\Integrations\StoreOrders;
use Illuminate\Http\Request;

/** Order webhooks from a customer's Shopify or WooCommerce store. */
class StoreWebhookController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $integration = Integration::withoutGlobalScopes()->where('token', $token)->whereIn('type', array_keys(Integration::STORES))->first();
        abort_unless($integration, 404);

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['ok' => true]); // WooCommerce sends a form-encoded ping when the webhook is created
        }
        if (! StoreOrders::verify($integration, $request)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }
        if (! $integration->active) {
            return response()->json(['ok' => true, 'ignored' => 'integration_off']);
        }

        $topic = (string) $request->header($integration->type === 'shopify' ? 'X-Shopify-Topic' : 'X-WC-Webhook-Topic');
        $order = StoreOrders::parse($integration->type, $topic, $payload);
        if (! $order) {
            return response()->json(['ok' => true, 'ignored' => 'topic']);
        }

        $integration->forceFill(['events_count' => $integration->events_count + 1, 'last_event_at' => now()])->save();
        ProcessStoreOrder::dispatch($integration->id, $order);

        return response()->json(['ok' => true]);
    }
}
