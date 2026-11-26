@extends('layouts.app', ['title' => 'Quick replies'])

@section('content')
<x-page-header title="Quick replies" subtitle="Saved answers your team can send in Live Chat by typing / and the shortcut.">
    <x-btn variant="outline" :href="route('inbox.index')">‹ Back to Live Chat</x-btn>
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('quick-replies.store') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-fit">
        @csrf
        <div class="font-semibold mb-4">New quick reply</div>
        <label class="block text-sm font-medium text-slate-600">Shortcut</label>
        <div class="flex items-center mt-1 rounded-xl border border-slate-200 focus-within:border-brand-400">
            <span class="pl-3 text-slate-400">/</span>
            <input name="shortcut" value="{{ old('shortcut') }}" placeholder="price" class="flex-1 px-2 py-2 text-sm outline-none rounded-xl" required>
        </div>
        <label class="block text-sm font-medium text-slate-600 mt-4">Message</label>
        <textarea name="body" rows="5" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Our plans start at ₹999/month. Shall I share the details?" required>{{ old('body') }}</textarea>
        <x-btn class="w-full mt-4" icon="plus">Save quick reply</x-btn>
    </form>

    <div class="xl:col-span-2 space-y-3">
        @forelse ($replies as $reply)
            <details class="bg-white rounded-2xl shadow-sm border border-slate-200 group">
                <summary class="flex items-start gap-3 p-4 cursor-pointer list-none">
                    <span class="text-sm font-semibold text-brand-700 bg-brand-50 rounded-lg px-2 py-1 shrink-0">/{{ $reply->shortcut }}</span>
                    <span class="text-sm text-slate-600 whitespace-pre-line flex-1">{{ $reply->body }}</span>
                    <x-icon name="pencil" class="w-4 h-4 text-slate-400 shrink-0 mt-1" />
                </summary>
                <div class="border-t border-slate-100 p-4 flex flex-col gap-3">
                    <form method="POST" action="{{ route('quick-replies.update', $reply) }}" class="space-y-2">
                        @csrf @method('PUT')
                        <input name="shortcut" value="{{ $reply->shortcut }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <textarea name="body" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>{{ $reply->body }}</textarea>
                        <x-btn>Update</x-btn>
                    </form>
                    <form method="POST" action="{{ route('quick-replies.destroy', $reply) }}">@csrf @method('DELETE')<x-btn variant="danger" icon="trash">Delete</x-btn></form>
                </div>
            </details>
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 text-center text-slate-500">
                <x-icon name="bolt" class="w-10 h-10 mx-auto text-slate-300" />
                <div class="mt-2">No quick replies yet. Add answers you type often, like prices or your address.</div>
            </div>
        @endforelse
    </div>
</div>
@endsection
