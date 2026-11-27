<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Api\Webhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** POST one event to a customer endpoint. Retries with growing waits; turns the endpoint off after many failures. */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->deliveryId);
        $endpoint = $delivery ? WebhookEndpoint::withoutGlobalScopes()->find($delivery->webhook_endpoint_id) : null;
        if (! $delivery || ! $endpoint || ! $endpoint->active || $delivery->status === 'success') {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $code = null;
        $text = null;
        try {
            $res = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'WhatsAppBusinessSuite-Webhooks/1.0',
                'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Id' => (string) $delivery->id,
                'X-Webhook-Signature' => Webhooks::signature($body, $endpoint->secret),
            ])->withBody($body, 'application/json')->post($endpoint->url);
            $code = $res->status();
            $text = Str::limit($res->body(), 480);
            $ok = $res->successful();
        } catch (Throwable $e) {
            $text = Str::limit($e->getMessage(), 480);
            $ok = false;
        }

        $delivery->update([
            'attempts' => $delivery->attempts + 1, 'response_code' => $code, 'response_body' => $text,
            'status' => $ok ? 'success' : ($this->attempts() >= $this->tries ? 'failed' : 'pending'),
            'delivered_at' => $ok ? now() : null,
        ]);
        $endpoint->forceFill([
            'last_delivery_at' => now(), 'last_status' => $code,
            'failures' => $ok ? 0 : $endpoint->failures + 1,
            'active' => $ok || $endpoint->failures + 1 < WebhookEndpoint::MAX_FAILURES,
        ])->save();

        if (! $ok && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
        }
    }
}
