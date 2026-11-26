<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsappWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    /** Meta calls this once when the webhook URL is saved in the app dashboard. */
    public function verify(Request $request)
    {
        $token = config('whatsapp.webhook_verify_token');
        if ($token && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** Store, queue, and answer 200 fast; Meta retries slow or failing endpoints. */
    public function receive(Request $request)
    {
        $secret = (string) config('whatsapp.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if ($secret === '' || ! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            Log::warning('WhatsApp webhook with bad signature');

            return response('Invalid signature', 401);
        }

        $event = WebhookEvent::create(['payload' => $request->json()->all()]);
        ProcessWhatsappWebhook::dispatch($event->id);

        return response('OK', 200);
    }
}
