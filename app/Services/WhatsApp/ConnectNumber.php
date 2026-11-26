<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/** Finishes onboarding of a customer's number after Embedded Signup. */
class ConnectNumber
{
    /**
     * $register is false for coexistence numbers (already live on the WhatsApp Business app),
     * which must not be registered again.
     */
    public function viaEmbeddedSignup(Workspace $workspace, string $code, string $wabaId, string $phoneNumberId, ?string $businessId = null, bool $register = true): WhatsappAccount
    {
        $token = (new CloudApi)->exchangeCode($code);

        return $this->finish($workspace, $token, $wabaId, $phoneNumberId, $businessId, $register ? 'embedded_signup' : 'coexistence', $register);
    }

    /** For numbers already set up in Meta (e.g. our own test number): paste ids and a token. */
    public function manually(Workspace $workspace, string $token, string $wabaId, string $phoneNumberId): WhatsappAccount
    {
        return $this->finish($workspace, $token, $wabaId, $phoneNumberId, null, 'manual', register: false);
    }

    private function finish(Workspace $workspace, string $token, string $wabaId, string $phoneNumberId, ?string $businessId, string $via, bool $register): WhatsappAccount
    {
        $api = new CloudApi($token);
        $existing = WhatsappAccount::withoutGlobalScopes()->where('phone_number_id', $phoneNumberId)->first();
        if ($existing && $existing->workspace_id !== $workspace->id) {
            throw new CloudApiException('This WhatsApp number is already connected to another account.');
        }

        $pin = $existing?->pin ?? (string) random_int(100000, 999999);
        $errors = [];

        // Lets our webhook receive this WABA's messages and statuses.
        $api->subscribeApp($wabaId);

        if ($register) {
            try {
                $api->registerPhone($phoneNumberId, $pin);
            } catch (CloudApiException $e) {
                // Coexistence numbers or numbers registered before can refuse this; keep going.
                $errors[] = 'register: '.$e->getMessage();
                Log::warning('WhatsApp phone register failed', ['phone_number_id' => $phoneNumberId, 'error' => $e->getMessage()]);
            }
        }

        $info = [];
        try {
            $info = $api->phoneNumber($phoneNumberId);
        } catch (CloudApiException $e) {
            $errors[] = 'details: '.$e->getMessage();
        }

        return WhatsappAccount::withoutGlobalScopes()->updateOrCreate(
            ['phone_number_id' => $phoneNumberId],
            [
                'workspace_id' => $workspace->id,
                'waba_id' => $wabaId,
                'business_id' => $businessId,
                'access_token' => $token,
                'pin' => $pin,
                'display_phone_number' => $info['display_phone_number'] ?? null,
                'verified_name' => $info['verified_name'] ?? null,
                'quality_rating' => $info['quality_rating'] ?? null,
                'messaging_limit' => $info['messaging_limit_tier'] ?? null,
                'status' => 'connected',
                'connected_via' => $via,
                'last_error' => $errors ? implode("\n", $errors) : null,
            ],
        );
    }
}
