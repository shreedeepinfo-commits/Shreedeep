<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\WhatsApp\CloudApiException;
use App\Services\WhatsApp\ConnectNumber;
use App\Support\Branding;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * White-label domains cannot run Meta Embedded Signup (Meta only allows the app's registered domain).
 * The reseller-domain page opens this page on the main domain in a popup with a short-lived encrypted
 * ticket naming the workspace; the number is connected here and the popup tells the opener it is done.
 */
class WhatsappSignupBridgeController extends Controller
{
    private const TTL = 1800; // seconds

    public static function ticket(Workspace $workspace, int $userId, string $returnOrigin): string
    {
        return Crypt::encryptString(json_encode(['w' => $workspace->id, 'u' => $userId, 'o' => $returnOrigin, 'e' => now()->timestamp + self::TTL]));
    }

    /** @return array{0: Workspace, 1: string} */
    private function redeem(?string $ticket): array
    {
        try {
            $t = json_decode(Crypt::decryptString((string) $ticket), true);
        } catch (DecryptException) {
            abort(403, 'This link is not valid. Please start again from WhatsApp Settings.');
        }
        abort_if(! is_array($t) || ($t['e'] ?? 0) < now()->timestamp, 403, 'This link has expired. Please start again from WhatsApp Settings.');
        $workspace = Workspace::find($t['w'] ?? 0);
        abort_unless($workspace && ! $workspace->isSuspended(), 404);

        return [$workspace, (string) $t['o']];
    }

    public function show(Request $request, Branding $branding)
    {
        abort_unless($request->getHost() === Branding::mainHost(), 404);
        [$workspace, $origin] = $this->redeem($request->query('t'));
        $branding->apply($workspace->reseller); // the popup shows the reseller's brand

        return view('whatsapp.bridge', [
            'ticket' => $request->query('t'),
            'origin' => $origin,
            'workspace' => $workspace,
            'coexistence' => $request->boolean('coexistence'),
            'signupReady' => config('whatsapp.app_id') && config('whatsapp.config_id'),
        ]);
    }

    public function connect(Request $request, ConnectNumber $connect, CurrentWorkspace $current)
    {
        abort_unless($request->getHost() === Branding::mainHost(), 404);
        $data = $request->validate([
            't' => ['required', 'string'],
            'code' => ['required', 'string'],
            'waba_id' => ['required', 'string', 'regex:/^\d+$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d+$/'],
            'business_id' => ['nullable', 'string', 'regex:/^\d+$/'],
            'coexistence' => ['nullable', 'boolean'],
        ]);
        [$workspace] = $this->redeem($data['t']);
        $current->set($workspace, 'owner');
        if ($limit = $this->planLimitReached('numbers')) {
            return $limit;
        }

        try {
            $account = $connect->viaEmbeddedSignup($workspace, $data['code'], $data['waba_id'], $data['phone_number_id'], $data['business_id'] ?? null,
                register: ! ($data['coexistence'] ?? false));
        } catch (CloudApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['label' => $account->label()]);
    }
}
