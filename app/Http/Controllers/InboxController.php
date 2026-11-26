<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Message;
use App\Models\QuickReply;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Multi-agent shared inbox: conversations, assignment, status, notes, tags, media and quick replies. */
class InboxController extends Controller
{
    public const TABS = [
        'all' => 'All',
        'mine' => 'Mine',
        'unassigned' => 'Unassigned',
        'open' => 'Open',
        'pending' => 'Pending',
        'resolved' => 'Resolved',
    ];

    /** Allowed attachments: WhatsApp media type => [extensions, max KB]. */
    private const MEDIA = [
        'image' => [['jpg', 'jpeg', 'png'], 5 * 1024],
        'video' => [['mp4', '3gp'], 16 * 1024],
        'audio' => [['mp3', 'aac', 'ogg', 'amr', 'm4a'], 16 * 1024],
        'document' => [['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'], 25 * 1024],
    ];

    public function index(Request $request, ?Contact $contact = null)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $q = trim((string) $request->query('q'));

        if ($contact) {
            $this->authorizeChat($contact);
        }
        if ($contact && $contact->unread_count) {
            $contact->forceFill(['unread_count' => 0])->save();
        }

        $data = [
            'tab' => $tab,
            'counts' => $this->counts(),
            'conversations' => $this->conversations($tab, $q),
            'contact' => $contact?->load(['assignedUser', 'notes.user']),
            'thread' => $contact ? $this->thread($contact) : collect(),
            'members' => $this->members(),
            'quickReplies' => QuickReply::orderBy('shortcut')->get(),
            'account' => $contact ? $this->accountFor($contact) : null,
        ];

        return view('inbox.index', $data);
    }

    /** Polled every few seconds by the chat screen. */
    public function poll(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $contact = $request->integer('contact') ? Contact::find($request->integer('contact')) : null;
        $after = $request->integer('after');

        $newMessages = collect();
        if ($contact && ! app(CurrentWorkspace::class)->isManager() && ! in_array($contact->assigned_user_id, [null, auth()->id()], true)) {
            $contact = null; // transferred away from this agent
        }
        if ($contact) {
            $newMessages = $this->thread($contact)->where('id', '>', $after)->values();
            if ($newMessages->where('direction', 'in')->isNotEmpty()) {
                $contact->forceFill(['unread_count' => 0])->save();
            }
        }

        $latestIn = Message::where('direction', 'in')->whereIn('contact_id', $this->visible(Contact::query())->select('id'))->latest('id')->first();

        return response()->json([
            'list' => view('inbox.partials.list', [
                'conversations' => $this->conversations($tab, trim((string) $request->query('q'))),
                'contact' => $contact, 'tab' => $tab,
            ])->render(),
            'counts' => $this->counts(),
            'messages' => $newMessages->map(fn ($m) => view('inbox.partials.message', ['m' => $m])->render())->all(),
            'last_id' => $newMessages->max('id') ?? $after,
            'latest_inbound' => $latestIn ? [
                'id' => $latestIn->id, 'contact_id' => $latestIn->contact_id,
                'name' => $latestIn->contact?->name ?: '+'.$latestIn->phone, 'body' => Str::limit($latestIn->body, 80),
            ] : null,
            'window_seconds' => $contact?->fresh()->windowSecondsLeft(),
        ]);
    }

    public function reply(Request $request, Contact $contact)
    {
        $this->authorizeChat($contact);
        $request->validate([
            'body' => ['nullable', 'string', 'max:4096', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:'.(25 * 1024)],
        ]);

        if ($contact->blocked_at) {
            return $this->fail($request, 'This number is blacklisted. Remove it from the blacklist in Contacts & CRM to message it.');
        }
        if ($contact->windowSecondsLeft() <= 0) {
            return $this->fail($request, 'The 24-hour window is closed. Send an approved template from Campaigns to restart the chat.');
        }
        $account = $this->accountFor($contact);
        if (! $account) {
            return $this->fail($request, 'Connect a WhatsApp number first.');
        }

        $body = trim((string) $request->input('body'));
        $file = $request->file('file');
        $media = null;

        try {
            if ($file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $type = collect(self::MEDIA)->search(fn ($rule) => in_array($ext, $rule[0], true));
                if (! $type) {
                    return $this->fail($request, 'This file type cannot be sent on WhatsApp.');
                }
                if ($file->getSize() > self::MEDIA[$type][1] * 1024) {
                    return $this->fail($request, ucfirst($type).' is too large for WhatsApp (max '.(self::MEDIA[$type][1] / 1024).' MB).');
                }
                $name = $file->getClientOriginalName();
                $mime = $file->getMimeType() ?: 'application/octet-stream';
                $path = $file->store('chat-media/'.$contact->workspace_id, 'local');
                $mediaId = $account->api()->uploadMedia($account->phone_number_id, Storage::disk('local')->path($path), $mime, $name);
                $result = $account->api()->sendMedia($account->phone_number_id, $contact->phone, $type, $mediaId, $body, $name);
                $media = ['type' => $type, 'path' => $path, 'mime' => $mime, 'name' => $name];
            } else {
                $result = $account->api()->sendText($account->phone_number_id, $contact->phone, $body);
            }
        } catch (CloudApiException $e) {
            return $this->fail($request, 'Send failed: '.$e->getMessage());
        } catch (ConnectionException) {
            return $this->fail($request, 'Could not reach WhatsApp. Check the internet connection and try again.');
        }

        $message = Message::create([
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
            'direction' => 'out',
            'wamid' => $result['messages'][0]['id'] ?? null,
            'phone' => $contact->phone,
            'type' => $media['type'] ?? 'text',
            'body' => $media ? trim('['.$media['type'].'] '.($body !== '' ? $body : $media['name'])) : $body,
            'media_path' => $media['path'] ?? null,
            'media_mime' => $media['mime'] ?? null,
            'media_name' => $media['name'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $contact->last_message_at = now();
        $contact->chat_status ??= 'open';
        if (! $contact->assigned_user_id) {
            $contact->assigned_user_id = $request->user()->id; // whoever replies first owns the chat
        }
        $contact->save();

        if ($request->expectsJson()) {
            return response()->json(['html' => view('inbox.partials.message', ['m' => $message])->render(), 'id' => $message->id]);
        }

        return redirect()->route('inbox.show', $contact);
    }

    public function assign(Request $request, Contact $contact)
    {
        $this->authorizeChat($contact);
        $data = $request->validate(['user_id' => ['nullable', Rule::in($this->members()->pluck('id')->all())]]);
        $userId = $data['user_id'] ?? null;
        if ((int) $contact->assigned_user_id === (int) $userId) {
            return back();
        }

        $contact->update(['assigned_user_id' => $userId]);
        $who = $userId ? User::find($userId)->name : 'nobody';
        $this->activity($contact, $request->user(), "Chat assigned to {$who}");

        $message = $userId ? "Chat assigned to {$who}." : 'Chat unassigned.';
        if (! app(CurrentWorkspace::class)->isManager() && $userId && (int) $userId !== auth()->id()) {
            return redirect()->route('inbox.index')->with('status', $message);
        }

        return back()->with('status', $message);
    }

    public function status(Request $request, Contact $contact)
    {
        $this->authorizeChat($contact);
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved']]);
        $contact->update(['chat_status' => $data['status']]);
        $this->activity($contact, $request->user(), 'Marked as '.$data['status']);

        return back()->with('status', 'Chat marked as '.$data['status'].'.');
    }

    public function tags(Request $request, Contact $contact)
    {
        $this->authorizeChat($contact);
        $data = $request->validate(['tags' => ['nullable', 'string', 'max:500']]);
        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($t) => Str::limit(trim($t), 30, ''))->filter()->unique(fn ($t) => mb_strtolower($t))->take(20)->values()->all();
        $contact->update(['tags' => $tags ?: null]);

        return back()->with('status', 'Tags saved.');
    }

    public function note(Request $request, Contact $contact)
    {
        $this->authorizeChat($contact);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        ContactNote::create(['contact_id' => $contact->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('status', 'Note added.');
    }

    public function destroyNote(Contact $contact, ContactNote $note)
    {
        $this->authorizeChat($contact);
        abort_unless($note->contact_id === $contact->id && ! $note->system, 404);
        $note->delete();

        return back()->with('status', 'Note deleted.');
    }

    /** Stream a message's image/video/audio/document; received media is fetched from Meta once and kept. */
    public function media(Message $message)
    {
        abort_unless(in_array($message->type, Message::MEDIA_TYPES, true), 404);
        if ($message->contact) {
            $this->authorizeChat($message->contact);
        }

        if (! $message->media_path) {
            $mediaId = $message->mediaId();
            abort_unless($mediaId && $message->whatsappAccount, 404);
            try {
                $file = $message->whatsappAccount->api()->downloadMedia($mediaId);
            } catch (CloudApiException|ConnectionException) {
                abort(404);
            }
            $path = 'chat-media/'.$message->workspace_id.'/in-'.$message->id.'-'.Str::random(8);
            Storage::disk('local')->put($path, $file['body']);
            $message->forceFill(['media_path' => $path, 'media_mime' => $message->media_mime ?: $file['mime']])->save();
        }

        abort_unless(Storage::disk('local')->exists($message->media_path), 404);
        $headers = ['Content-Type' => $message->media_mime ?: 'application/octet-stream', 'Cache-Control' => 'private, max-age=86400'];

        return $message->type === 'document'
            ? Storage::disk('local')->download($message->media_path, $message->media_name ?: 'document', $headers)
            : Storage::disk('local')->response($message->media_path, null, $headers);
    }

    /** Agents only see chats assigned to them or not assigned yet. */
    private function authorizeChat(Contact $contact): void
    {
        if (! app(CurrentWorkspace::class)->isManager()) {
            abort_unless(in_array($contact->assigned_user_id, [null, auth()->id()], true), 403, 'This chat is assigned to another agent.');
        }
    }

    private function visible(Builder $query): Builder
    {
        if (! app(CurrentWorkspace::class)->isManager()) {
            $query->where(fn ($w) => $w->whereNull('assigned_user_id')->orWhere('assigned_user_id', auth()->id()));
        }

        return $query;
    }

    private function conversations(string $tab, string $q)
    {
        $userId = auth()->id();

        return Contact::with('assignedUser')
            ->whereNotNull('last_message_at')
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->when($digits !== '', fn ($w) => $w->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->tap(fn (Builder $query) => $this->applyTab($this->visible($query), $tab, $userId))
            ->orderByDesc('last_message_at')->limit(100)->get();
    }

    private function applyTab(Builder $query, string $tab, ?int $userId): void
    {
        match ($tab) {
            'mine' => $query->where('assigned_user_id', $userId)->where(fn ($w) => $w->whereNull('chat_status')->orWhere('chat_status', '!=', 'resolved')),
            'unassigned' => $query->whereNull('assigned_user_id')->where(fn ($w) => $w->whereNull('chat_status')->orWhere('chat_status', '!=', 'resolved')),
            'open', 'pending', 'resolved' => $query->where('chat_status', $tab),
            default => null,
        };
    }

    private function counts(): array
    {
        $userId = auth()->id();

        return collect(array_keys(self::TABS))->mapWithKeys(function ($tab) use ($userId) {
            $query = $this->visible(Contact::whereNotNull('last_message_at'));
            $this->applyTab($query, $tab, $userId);

            return [$tab => $query->count()];
        })->put('unread', (int) $this->visible(Contact::query())->sum('unread_count'))->all();
    }

    private function thread(Contact $contact)
    {
        return Message::with('user')->where('contact_id', $contact->id)
            ->where(fn ($q) => $q->where('direction', 'in')->orWhereNull('campaign_id')->orWhere('status', '!=', 'queued'))
            ->orderByDesc('id')->limit(200)->get()->reverse()->values();
    }

    private function members()
    {
        return app(CurrentWorkspace::class)->get()?->users()->orderBy('name')->get() ?? collect();
    }

    /** Reply from the number the customer last wrote to. */
    private function accountFor(Contact $contact): ?WhatsappAccount
    {
        $lastIn = Message::where('contact_id', $contact->id)->where('direction', 'in')->latest('id')->first();

        return $lastIn?->whatsappAccount ?? WhatsappAccount::where('status', 'connected')->first() ?? WhatsappAccount::first();
    }

    private function activity(Contact $contact, User $user, string $text): void
    {
        ContactNote::create(['contact_id' => $contact->id, 'user_id' => $user->id, 'body' => $text, 'system' => true]);
    }

    private function fail(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors(['body' => $message])->withInput();
    }
}
