<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    /** Conversations = contacts who wrote to us, newest first. */
    public function index(Request $request, ?Contact $contact = null)
    {
        $conversations = Contact::whereNotNull('last_message_at')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")))
            ->orderByDesc('last_message_at')->limit(100)->get();

        $thread = collect();
        $windowOpen = false;
        $account = null;
        if ($contact) {
            $thread = Message::where('contact_id', $contact->id)
                ->where(fn ($q) => $q->where('direction', 'in')->orWhereNull('campaign_id')->orWhere('status', '!=', 'queued'))
                ->orderByDesc('id')->limit(200)->get()->reverse();
            $lastIn = $thread->where('direction', 'in')->last();
            $windowOpen = $lastIn && $lastIn->created_at->gt(now()->subHours(24));
            $account = $lastIn?->whatsappAccount ?? WhatsappAccount::first();
        }

        return view('inbox.index', compact('conversations', 'contact', 'thread', 'windowOpen', 'account'));
    }

    public function reply(Request $request, Contact $contact)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']]);

        $lastIn = Message::where('contact_id', $contact->id)->where('direction', 'in')->latest('id')->first();
        if (! $lastIn || $lastIn->created_at->lt(now()->subHours(24))) {
            return back()->withErrors(['body' => 'The 24-hour window is closed. Send an approved template from Campaigns to restart the chat.']);
        }

        $account = $lastIn->whatsappAccount;
        try {
            $result = $account->api()->sendText($account->phone_number_id, $contact->phone, $data['body']);
        } catch (CloudApiException $e) {
            return back()->withErrors(['body' => 'Send failed: '.$e->getMessage()])->withInput();
        }

        Message::create([
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'out',
            'wamid' => $result['messages'][0]['id'] ?? null,
            'phone' => $contact->phone,
            'type' => 'text',
            'body' => $data['body'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $contact->update(['last_message_at' => now()]);

        return redirect()->route('inbox.show', $contact);
    }
}
