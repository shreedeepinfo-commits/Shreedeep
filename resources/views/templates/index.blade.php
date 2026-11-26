@extends('layouts.app', ['title' => 'Templates'])

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Message templates</h1>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('templates.sync') }}">@csrf<button class="rounded border px-4 py-2" @disabled($accounts->isEmpty())>Sync from Meta</button></form>
        <a href="{{ route('templates.create') }}" class="rounded bg-brand-600 text-white px-4 py-2 font-semibold">New template</a>
    </div>
</div>

@if ($accounts->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center text-slate-600">Connect a WhatsApp number first.</div>
@else
<div class="flex gap-2 mb-4 text-sm">
    @foreach (['' => 'All', 'APPROVED' => 'Approved', 'PENDING' => 'Pending', 'REJECTED' => 'Rejected'] as $s => $label)
        <a href="?status={{ $s }}" class="px-3 py-1 rounded {{ request('status', '') === $s ? 'bg-brand-600 text-white' : 'bg-white border' }}">{{ $label }}</a>
    @endforeach
</div>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse ($templates as $t)
        @php $color = ['APPROVED' => 'bg-emerald-100 text-emerald-800', 'REJECTED' => 'bg-red-100 text-red-800'][$t->status] ?? 'bg-amber-100 text-amber-800'; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-semibold font-mono">{{ $t->name }}</div>
                    <div class="text-xs text-slate-500">{{ $t->language }} · {{ $t->category }} · {{ $t->whatsappAccount->display_phone_number }}</div>
                </div>
                <span class="text-xs px-2 py-0.5 rounded {{ $color }}">{{ $t->status }}</span>
            </div>
            <div class="mt-3 rounded-lg bg-[#e7ffdb] p-3 text-sm whitespace-pre-line flex-1">
                @if ($h = $t->component('HEADER'))<div class="font-semibold mb-1">{{ $h['text'] ?? '['.($h['format'] ?? 'MEDIA').']' }}</div>@endif
                {{ $t->bodyText() }}
                @if ($f = $t->component('FOOTER'))<div class="text-xs text-slate-500 mt-1">{{ $f['text'] }}</div>@endif
                @foreach ($t->component('BUTTONS')['buttons'] ?? [] as $b)<div class="mt-1 text-center text-sky-700 border-t pt-1">{{ $b['text'] }}</div>@endforeach
            </div>
            @if ($t->rejected_reason)<div class="text-xs text-red-700 mt-2">Rejected: {{ $t->rejected_reason }}</div>@endif
            <form method="POST" action="{{ route('templates.destroy', $t) }}" class="mt-3 text-right" onsubmit="return confirm('Delete this template in Meta too?')">@csrf @method('DELETE')<button class="text-xs text-red-700 underline">Delete</button></form>
        </div>
    @empty
        <div class="text-slate-600">No templates yet. Click <b>Sync from Meta</b> or create one.</div>
    @endforelse
</div>
@endif
@endsection
