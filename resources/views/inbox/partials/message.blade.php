@php
    $out = $m->direction === 'out';
    $tick = ['read' => '✓✓', 'delivered' => '✓✓', 'sent' => '✓', 'failed' => '!', 'queued' => '🕓'][$m->status] ?? '';
    $caption = $m->hasMedia() ? trim(preg_replace('/^\[(image|video|audio|document|sticker)\]\s*/', '', (string) $m->body)) : null;
@endphp
<div class="flex {{ $out ? 'justify-end' : 'justify-start' }}" data-id="{{ $m->id }}">
    <div class="max-w-[75%] rounded-2xl px-3 py-2 text-sm shadow-sm {{ $out ? 'bg-[#d9fdd3] rounded-tr-md' : 'bg-white rounded-tl-md' }}">
        @if ($m->hasMedia())
            @php $src = route('inbox.media', $m); @endphp
            @if (in_array($m->type, ['image', 'sticker']))
                <a href="{{ $src }}" target="_blank"><img src="{{ $src }}" alt="" class="rounded-xl max-h-64 mb-1" loading="lazy"></a>
            @elseif ($m->type === 'video')
                <video src="{{ $src }}" controls class="rounded-xl max-h-64 mb-1"></video>
            @elseif ($m->type === 'audio')
                <audio src="{{ $src }}" controls class="mb-1 max-w-full"></audio>
            @else
                <a href="{{ $src }}" class="flex items-center gap-2 rounded-xl bg-black/5 px-3 py-2 mb-1 hover:bg-black/10">
                    <x-icon name="document" class="w-6 h-6 text-brand-600" />
                    <span class="truncate font-medium">{{ $m->media_name ?: 'Document' }}</span>
                </a>
            @endif
            @if ($caption !== '' && $caption !== $m->media_name)<div class="whitespace-pre-line break-words">{{ $caption }}</div>@endif
        @else
            <div class="whitespace-pre-line break-words">{{ $m->body ?: '['.$m->type.']' }}</div>
        @endif
        <div class="flex items-center justify-end gap-1 text-[10px] text-slate-500 mt-1">
            @if ($m->campaign_id)<span class="px-1.5 rounded bg-brand-50 text-brand-700">Campaign</span>@endif
            @if ($out && $m->type === 'interactive' && ! $m->user_id)<span class="px-1.5 rounded bg-violet-50 text-violet-700">Bot</span>@endif
            @if ($out && $m->user)<span>{{ Str::before($m->user->name, ' ') }} ·</span>@endif
            <span>{{ $m->created_at->format('d M, h:i A') }}</span>
            @if ($out)<span class="{{ $m->status === 'read' ? 'text-sky-500' : ($m->status === 'failed' ? 'text-red-600 font-bold' : '') }}" title="{{ ucfirst($m->status) }}{{ $m->error_message ? ': '.$m->error_message : '' }}">{{ $tick }}</span>@endif
        </div>
    </div>
</div>
