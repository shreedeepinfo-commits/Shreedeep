<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhook;
use App\Models\ApiKey;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** API & Webhooks page: keys, outgoing webhooks, delivery log and docs. */
class DeveloperController extends Controller
{
    public function index(Request $request)
    {
        return view('developers.index', [
            'tab' => in_array($request->query('tab'), ['keys', 'webhooks', 'docs'], true) ? $request->query('tab') : 'keys',
            'keys' => ApiKey::whereNull('revoked_at')->latest('id')->get(),
            'endpoints' => WebhookEndpoint::latest('id')->get(),
            'deliveries' => WebhookDelivery::with('endpoint')->latest('id')->limit(50)->get(),
            'baseUrl' => url('/api/v1'),
        ]);
    }

    public function storeKey(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        if (ApiKey::whereNull('revoked_at')->count() >= 10) {
            return back()->withErrors(['name' => 'You can have up to 10 active keys. Revoke one you do not use.']);
        }
        [, $plain] = ApiKey::generate($data['name'], $request->user()->id);

        return redirect()->route('developers.index')->with('new_key', $plain)->with('status', 'API key created. Copy it now: it will not be shown again.');
    }

    public function revokeKey(ApiKey $key)
    {
        $key->update(['revoked_at' => now()]);

        return back()->with('status', "Key \"{$key->name}\" revoked. Apps using it stop working right away.");
    }

    public function storeWebhook(Request $request)
    {
        $data = $this->validatedWebhook($request);
        if (WebhookEndpoint::count() >= 5) {
            return back()->withErrors(['url' => 'You can add up to 5 webhook URLs.']);
        }
        WebhookEndpoint::create($data + ['secret' => 'whsec_'.Str::random(32), 'active' => true]);

        return redirect()->route('developers.index', ['tab' => 'webhooks'])->with('status', 'Webhook added.');
    }

    public function updateWebhook(Request $request, WebhookEndpoint $endpoint)
    {
        $endpoint->update($this->validatedWebhook($request) + ['active' => true, 'failures' => 0]);

        return redirect()->route('developers.index', ['tab' => 'webhooks'])->with('status', 'Webhook saved and turned on.');
    }

    public function toggleWebhook(WebhookEndpoint $endpoint)
    {
        $endpoint->update(['active' => ! $endpoint->active, 'failures' => 0]);

        return back()->with('status', 'Webhook turned '.($endpoint->active ? 'on' : 'off').'.');
    }

    public function destroyWebhook(WebhookEndpoint $endpoint)
    {
        $endpoint->delete();

        return redirect()->route('developers.index', ['tab' => 'webhooks'])->with('status', 'Webhook deleted.');
    }

    /** Send a sample event now so the customer can check their server. */
    public function testWebhook(WebhookEndpoint $endpoint)
    {
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id, 'event' => 'ping',
            'payload' => ['event' => 'ping', 'created_at' => now()->toIso8601String(), 'data' => ['message' => 'Test from your WhatsApp panel. It works!']],
        ]);
        DeliverWebhook::dispatchSync($delivery->id);
        $delivery->refresh();

        return redirect()->route('developers.index', ['tab' => 'webhooks'])->with('status', $delivery->status === 'success'
            ? "Test delivered (HTTP {$delivery->response_code})."
            : 'Test failed'.($delivery->response_code ? " (HTTP {$delivery->response_code})" : '').': '.Str::limit((string) $delivery->response_body, 120).' Check the URL and send the test again.');
    }

    public function retryDelivery(WebhookDelivery $delivery)
    {
        $delivery->update(['status' => 'pending']);
        DeliverWebhook::dispatch($delivery->id);

        return back()->with('status', 'Delivery queued again.');
    }

    private function validatedWebhook(Request $request): array
    {
        $data = $request->validate([
            'url' => ['required', 'url:https,http', 'max:500', function ($attr, $value, $fail) {
                if (app()->environment('local', 'testing')) {
                    return; // allow http://localhost while building
                }
                $host = (string) parse_url($value, PHP_URL_HOST);
                $privateIp = filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if (! str_starts_with($value, 'https://') || $host === 'localhost' || $privateIp) {
                    $fail('Use a public https:// URL.');
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(array_keys(WebhookEndpoint::EVENTS))],
        ], ['events.required' => 'Choose at least one event.']);

        return ['url' => $data['url'], 'events' => array_values($data['events'])];
    }
}
