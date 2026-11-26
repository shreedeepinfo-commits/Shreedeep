@extends('layouts.app', ['title' => 'Team Management'])

@php
    $fmtTime = fn ($s) => $s === null ? '—' : ($s < 60 ? $s.'s' : ($s < 3600 ? intdiv($s, 60).'m' : intdiv($s, 3600).'h '.intdiv($s % 3600, 60).'m'));
    $roleColors = ['owner' => 'bg-brand-600 text-white', 'admin' => 'bg-brand-50 text-brand-700', 'agent' => 'bg-emerald-50 text-emerald-700'];
@endphp

@section('content')
<x-page-header title="Team Management" subtitle="Invite your team, set who can do what and share chats fairly." />

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Team members" :value="$members->count()" icon="team" />
    <x-stat-card label="Agents" :value="$members->where('pivot.role', 'agent')->count()" icon="chat" />
    <x-stat-card label="Pending invites" :value="$invitations->count()" icon="send" />
    <x-stat-card label="Open chats assigned" :value="$report->sum('open')" icon="users" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        {{-- Members --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-200 font-semibold">Members</div>
            <div class="divide-y divide-slate-100">
                @foreach ($members as $member)
                    @php $role = $member->pivot->role; @endphp
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <span class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 grid place-items-center font-semibold uppercase">{{ mb_substr($member->name, 0, 1) }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium">{{ $member->name }} @if ($member->id === auth()->id())<span class="text-xs text-slate-400">(you)</span>@endif</div>
                            <div class="text-xs text-slate-500 truncate">{{ $member->email }}</div>
                        </div>
                        @if ($role === 'owner' || $member->id === auth()->id())
                            <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $roleColors[$role] ?? '' }}">{{ $roles[$role] ?? ucfirst($role) }}</span>
                        @else
                            <form method="POST" action="{{ route('team.role', $member) }}">
                                @csrf @method('PUT')
                                <select name="role" onchange="this.form.submit()" class="text-sm rounded-xl border border-slate-200 px-3 py-1.5">
                                    <option value="admin" @selected($role === 'admin')>Admin</option>
                                    <option value="agent" @selected($role === 'agent')>Agent</option>
                                </select>
                            </form>
                            <form method="POST" action="{{ route('team.remove', $member) }}" onsubmit="return confirm(@js('Remove '.$member->name.' from the team?'))">
                                @csrf @method('DELETE')
                                <button class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50" title="Remove"><x-icon name="trash" class="w-4 h-4" /></button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pending invitations --}}
        @if ($invitations->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="px-5 py-4 border-b border-slate-200 font-semibold">Pending invitations</div>
                <div class="divide-y divide-slate-100">
                    @foreach ($invitations as $invite)
                        <div class="px-5 py-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium">{{ $invite->name }} <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $roleColors[$invite->role] ?? '' }}">{{ $roles[$invite->role] }}</span></div>
                                    <div class="text-xs text-slate-500">{{ $invite->email }} · {{ $invite->expires_at->isPast() ? 'expired' : 'expires '.$invite->expires_at->diffForHumans() }}</div>
                                </div>
                                <a target="_blank" href="https://wa.me/?text={{ urlencode('You are invited to join '.$currentWorkspace->name.' on '.config('brand.product').'. Open this link to join: '.$invite->url()) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100"><x-icon name="whatsapp" class="w-4 h-4" /> Share</a>
                                <form method="POST" action="{{ route('team.invitations.destroy', $invite) }}">@csrf @method('DELETE')<button class="text-xs font-semibold px-3 py-1.5 rounded-lg text-red-700 hover:bg-red-50">Cancel</button></form>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <input readonly value="{{ $invite->url() }}" class="flex-1 text-xs rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5" onclick="this.select()">
                                <button type="button" class="text-xs font-semibold px-3 rounded-lg bg-slate-100 hover:bg-slate-200" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='Copied!'">Copy link</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Agent performance --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 font-semibold">Agent performance <span class="text-sm font-normal text-slate-400">last 30 days</span></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <tr><th class="text-left px-5 py-3">Member</th><th class="text-right px-5 py-3">Open chats</th><th class="text-right px-5 py-3">Replies sent</th><th class="text-right px-5 py-3">Resolved</th><th class="text-right px-5 py-3">Avg. first response</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium">{{ $row['user']->name }}</td>
                                <td class="px-5 py-3 text-right">{{ $row['open'] }}</td>
                                <td class="px-5 py-3 text-right">{{ number_format($row['replies']) }}</td>
                                <td class="px-5 py-3 text-right">{{ $row['resolved'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold {{ $row['avg_response'] !== null && $row['avg_response'] <= 300 ? 'text-emerald-600' : '' }}">{{ $fmtTime($row['avg_response']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Invite --}}
        <form method="POST" action="{{ route('team.invite') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            @csrf
            <div class="font-semibold mb-4 flex items-center gap-2"><x-icon name="user-plus" class="w-5 h-5 text-brand-600" /> Invite a team member</div>
            <label class="block text-sm font-medium text-slate-600">Name</label>
            <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
            <label class="block text-sm font-medium text-slate-600 mt-3">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
            <label class="block text-sm font-medium text-slate-600 mt-3">Role</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
                @foreach (['agent' => 'Chats with customers', 'admin' => 'Full access'] as $value => $hint)
                    <label class="rounded-xl border border-slate-200 p-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="role" value="{{ $value }}" class="sr-only" @checked(old('role', 'agent') === $value)>
                        <div class="font-semibold text-sm">{{ $roles[$value] }}</div>
                        <div class="text-xs text-slate-500">{{ $hint }}</div>
                    </label>
                @endforeach
            </div>
            <x-btn class="w-full mt-4" icon="send">Create invite link</x-btn>
            <p class="text-xs text-slate-400 mt-2">You get a link to send on WhatsApp or email. It works for 7 days.</p>
        </form>

        {{-- Auto assignment --}}
        <form method="POST" action="{{ route('team.settings') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            @csrf @method('PUT')
            <div class="font-semibold mb-1">Automatic chat assignment</div>
            <p class="text-sm text-slate-500">New customer chats go to agents one by one (round-robin), so nobody is overloaded.</p>
            <label class="flex items-center gap-3 mt-4 cursor-pointer">
                <input type="hidden" name="auto_assign" value="0">
                <input type="checkbox" name="auto_assign" value="1" class="peer sr-only" onchange="this.form.submit()" @checked($workspace->auto_assign)>
                <span class="w-11 h-6 rounded-full bg-slate-200 peer-checked:bg-brand-600 relative transition after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                <span class="text-sm font-medium">{{ $workspace->auto_assign ? 'On: round-robin' : 'Off' }}</span>
            </label>
        </form>

        {{-- Roles --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 text-sm">
            <div class="font-semibold mb-3">Who can do what</div>
            <ul class="space-y-2 text-slate-600">
                <li><b class="text-slate-800">Owner / Admin:</b> everything: numbers, campaigns, templates, chatbots, team.</li>
                <li><b class="text-slate-800">Agent:</b> Live Chat (own and unassigned chats), contacts and quick replies.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
