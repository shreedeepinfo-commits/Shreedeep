<?php

namespace App\Http\Controllers;

use App\Models\ContactNote;
use App\Models\Contact;
use App\Models\Message;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index()
    {
        $workspace = $this->current->get();
        $members = $workspace->users()->orderBy('name')->get();

        return view('team.index', [
            'workspace' => $workspace,
            'members' => $members,
            'invitations' => TeamInvitation::latest()->get(),
            'report' => $this->report($members),
            'roles' => CurrentWorkspace::ROLES,
        ]);
    }

    public function invite(Request $request)
    {
        $workspace = $this->current->get();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('team_invitations')->where('workspace_id', $workspace->id)],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ], ['email.unique' => 'This person is already invited.']);
        $data['email'] = Str::lower($data['email']);

        if ($workspace->users()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'This person is already in your team.'])->withInput();
        }

        $invitation = TeamInvitation::create($data + [
            'token' => Str::random(48),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('status', "Invitation created for {$invitation->name}. Copy the link below and send it to them.");
    }

    public function cancelInvite(TeamInvitation $invitation)
    {
        $invitation->delete();

        return back()->with('status', 'Invitation cancelled.');
    }

    public function updateRole(Request $request, User $member)
    {
        $workspace = $this->current->get();
        $pivot = $this->membership($member);
        abort_if($pivot->role === 'owner', 403, 'The owner role cannot be changed.');
        $data = $request->validate(['role' => ['required', Rule::in($this->assignableRoles())]]);

        $workspace->users()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return back()->with('status', "{$member->name} is now ".CurrentWorkspace::ROLES[$data['role']].'.');
    }

    public function remove(Request $request, User $member)
    {
        $workspace = $this->current->get();
        $pivot = $this->membership($member);
        abort_if($pivot->role === 'owner', 403, 'The owner cannot be removed.');
        abort_if($member->id === $request->user()->id, 403, 'You cannot remove yourself.');
        abort_if($pivot->role === 'admin' && $this->current->role() !== 'owner', 403, 'Only the owner can remove an admin.');

        $workspace->users()->detach($member->id);
        Contact::where('assigned_user_id', $member->id)->update(['assigned_user_id' => null]);
        if ($member->current_workspace_id === $workspace->id) {
            $member->forceFill(['current_workspace_id' => null])->save();
        }

        return back()->with('status', "{$member->name} was removed. Their chats are now unassigned.");
    }

    public function settings(Request $request)
    {
        $this->current->get()->update(['auto_assign' => $request->boolean('auto_assign')]);

        return back()->with('status', $request->boolean('auto_assign') ? 'New chats will be shared among agents one by one.' : 'Automatic assignment is off.');
    }

    /** Admins can add agents and admins; only the owner role is fixed. */
    private function assignableRoles(): array
    {
        return ['admin', 'agent'];
    }

    private function membership(User $member)
    {
        $found = $this->current->get()->users()->whereKey($member->id)->first();
        abort_unless($found, 404);

        return $found->pivot;
    }

    /** Last 30 days per member: open chats, replies, resolved chats and average first response time. */
    private function report(Collection $members): Collection
    {
        $since = now()->subDays(30);
        $replies = Message::where('direction', 'out')->whereNotNull('user_id')->where('created_at', '>=', $since)
            ->get(['id', 'user_id', 'contact_id', 'created_at']);
        $inbound = Message::where('direction', 'in')->where('created_at', '>=', $since->copy()->subDay())
            ->whereIn('contact_id', $replies->pluck('contact_id')->unique())
            ->orderBy('created_at')->get(['contact_id', 'created_at'])->groupBy('contact_id');
        $outByContact = Message::where('direction', 'out')->where('created_at', '>=', $since->copy()->subDay())
            ->whereIn('contact_id', $replies->pluck('contact_id')->unique())
            ->orderBy('created_at')->get(['contact_id', 'created_at'])->groupBy('contact_id');

        // First response = an agent reply that is the first outbound message after a customer message.
        $responseTimes = [];
        foreach ($replies as $reply) {
            $lastIn = ($inbound[$reply->contact_id] ?? collect())->filter(fn ($m) => $m->created_at->lte($reply->created_at))->last();
            if (! $lastIn) {
                continue;
            }
            $answeredBefore = ($outByContact[$reply->contact_id] ?? collect())
                ->contains(fn ($m) => $m->created_at->gt($lastIn->created_at) && $m->created_at->lt($reply->created_at));
            if (! $answeredBefore) {
                $responseTimes[$reply->user_id][] = $lastIn->created_at->diffInSeconds($reply->created_at);
            }
        }

        $resolved = ContactNote::where('system', true)->where('body', 'Marked as resolved')->where('created_at', '>=', $since)
            ->selectRaw('user_id, count(*) as c')->groupBy('user_id')->pluck('c', 'user_id');
        $open = Contact::whereNotNull('assigned_user_id')->where(fn ($q) => $q->whereNull('chat_status')->orWhere('chat_status', '!=', 'resolved'))
            ->selectRaw('assigned_user_id, count(*) as c')->groupBy('assigned_user_id')->pluck('c', 'assigned_user_id');

        return $members->map(fn (User $m) => [
            'user' => $m,
            'open' => (int) ($open[$m->id] ?? 0),
            'replies' => $replies->where('user_id', $m->id)->count(),
            'resolved' => (int) ($resolved[$m->id] ?? 0),
            'avg_response' => isset($responseTimes[$m->id]) ? (int) round(array_sum($responseTimes[$m->id]) / count($responseTimes[$m->id])) : null,
        ]);
    }
}
