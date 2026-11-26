<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use App\Services\WhatsApp\ConnectNumber;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;

class WhatsappAccountController extends Controller
{
    public function index()
    {
        return view('whatsapp.index', [
            'accounts' => WhatsappAccount::latest()->get(),
            'signupReady' => config('whatsapp.app_id') && config('whatsapp.config_id'),
        ]);
    }

    /** Called by the Embedded Signup popup JS with the code and the ids Meta sent. */
    public function connect(Request $request, ConnectNumber $connect, CurrentWorkspace $current)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'waba_id' => ['required', 'string', 'regex:/^\d+$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d+$/'],
            'business_id' => ['nullable', 'string', 'regex:/^\d+$/'],
            'coexistence' => ['nullable', 'boolean'],
        ]);
        if ($limit = $this->planLimitReached('numbers')) {
            return $limit;
        }

        try {
            $account = $connect->viaEmbeddedSignup(
                $current->get(), $data['code'], $data['waba_id'], $data['phone_number_id'], $data['business_id'] ?? null,
                register: ! ($data['coexistence'] ?? false),
            );
        } catch (CloudApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        session()->flash('status', 'WhatsApp number '.$account->label().' connected.');

        return response()->json(['redirect' => route('whatsapp.index')]);
    }

    public function storeManual(Request $request, ConnectNumber $connect, CurrentWorkspace $current)
    {
        $data = $request->validate([
            'waba_id' => ['required', 'string', 'regex:/^\d+$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d+$/'],
            'access_token' => ['required', 'string', 'min:20'],
        ]);
        if ($limit = $this->planLimitReached('numbers', 1, 'access_token')) {
            return $limit;
        }

        try {
            $account = $connect->manually($current->get(), $data['access_token'], $data['waba_id'], $data['phone_number_id']);
        } catch (CloudApiException $e) {
            return back()->withErrors(['access_token' => $e->getMessage()])->withInput($request->except('access_token'));
        }

        return redirect()->route('whatsapp.index')->with('status', 'WhatsApp number '.$account->label().' connected.');
    }

    public function sendTest(Request $request, WhatsappAccount $account)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'regex:/^\+?\d{8,15}$/'],
            'type' => ['required', 'in:template,text'],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'template' => ['required_if:type,template', 'nullable', 'string', 'max:512'],
            'language' => ['nullable', 'string', 'max:15'],
        ]);
        $to = ltrim($data['to'], '+');

        try {
            $result = $data['type'] === 'text'
                ? $account->api()->sendText($account->phone_number_id, $to, $data['body'])
                : $account->api()->sendTemplate($account->phone_number_id, $to, $data['template'], $data['language'] ?: 'en_US');
        } catch (CloudApiException $e) {
            return back()->withErrors(['to' => 'Send failed: '.$e->getMessage()])->withInput();
        }

        return back()->with('status', 'Message sent. WhatsApp message id: '.($result['messages'][0]['id'] ?? 'n/a'));
    }

    public function refresh(WhatsappAccount $account)
    {
        try {
            $info = $account->api()->phoneNumber($account->phone_number_id);
            $account->update([
                'display_phone_number' => $info['display_phone_number'] ?? $account->display_phone_number,
                'verified_name' => $info['verified_name'] ?? $account->verified_name,
                'quality_rating' => $info['quality_rating'] ?? null,
                'messaging_limit' => $info['messaging_limit_tier'] ?? null,
                'status' => 'connected',
                'last_error' => null,
            ]);
        } catch (CloudApiException $e) {
            $account->update(['status' => 'error', 'last_error' => $e->getMessage()]);

            return back()->withErrors(['refresh' => $e->getMessage()]);
        }

        return back()->with('status', 'Number details refreshed.');
    }

    public function destroy(WhatsappAccount $account)
    {
        $account->delete();

        return redirect()->route('whatsapp.index')->with('status', 'Number removed from the panel.');
    }
}
