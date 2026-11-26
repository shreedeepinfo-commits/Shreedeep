<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** Accepting a team invitation link (works signed in or out). */
class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->find($token);
        $existing = User::where('email', $invitation->email)->first();

        return view('team.accept', compact('invitation', 'existing'));
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->find($token);
        $user = User::where('email', $invitation->email)->first();

        if ($user) {
            // An existing account must be signed in as that same person.
            if (! Auth::check() || Auth::id() !== $user->id) {
                return redirect()->route('login')->withErrors(['email' => "Log in as {$invitation->email} to accept the invitation, then open the link again."]);
            }
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]);
            $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
        }

        DB::transaction(function () use ($invitation, $user) {
            $invitation->workspace->users()->syncWithoutDetaching([$user->id => ['role' => $invitation->role]]);
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();
            $invitation->delete();
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('inbox.index')->with('status', "Welcome to {$invitation->workspace->name}!");
    }

    private function find(string $token): TeamInvitation
    {
        $invitation = TeamInvitation::withoutGlobalScopes()->with('workspace')->where('token', $token)->first();
        abort_if(! $invitation || $invitation->expires_at->isPast(), 404, 'This invitation link is invalid or has expired.');

        return $invitation;
    }
}
