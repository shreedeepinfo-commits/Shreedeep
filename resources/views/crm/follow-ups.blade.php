@extends('layouts.app', ['title' => 'Follow-ups'])

@section('content')
<x-page-header title="Contacts & CRM" subtitle="Reminders to call or message your leads. Set them on any contact's page.">
    <x-btn variant="outline" :href="route('crm.followups', $mine ? [] : ['mine' => 1])">{{ $mine ? 'Show everyone\'s' : 'Only mine' }}</x-btn>
</x-page-header>

@include('contacts.partials.tabs')

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @foreach ($groups as $title => $items)
        @php $tone = ['Overdue' => 'text-red-600 bg-red-50', 'Today' => 'text-amber-700 bg-amber-50', 'Upcoming' => 'text-brand-700 bg-brand-50'][$title]; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center gap-2">
                <span class="font-semibold">{{ $title }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $tone }}">{{ $items->count() }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($items as $c)
                    <div class="px-5 py-4 flex items-start gap-3">
                        <span class="w-9 h-9 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-semibold uppercase shrink-0">{{ mb_substr($c->name ?: '#', 0, 1) }}</span>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('contacts.show', $c) }}" class="font-medium hover:text-brand-700 truncate block">{{ $c->displayName() }}</a>
                            <div class="text-xs {{ $title === 'Overdue' ? 'text-red-600' : 'text-slate-500' }}">📅 {{ $c->follow_up_at->format('d M, h:i A') }} @if ($c->assignedUser)· {{ $c->assignedUser->name }}@endif</div>
                            @if ($c->follow_up_note)<div class="text-sm text-slate-700 mt-1">{{ $c->follow_up_note }}</div>@endif
                            <div class="flex gap-3 mt-2 text-xs font-semibold">
                                <a href="{{ route('inbox.show', $c) }}" class="text-brand-700 hover:underline">Chat</a>
                                <a href="tel:+{{ $c->phone }}" class="text-brand-700 hover:underline">Call</a>
                                <form method="POST" action="{{ route('crm.followups.done', $c) }}">@csrf<button class="text-emerald-700 hover:underline">✓ Done</button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400">Nothing here.</div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@endsection
