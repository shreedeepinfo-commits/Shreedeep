@props(['header' => null, 'headerFormat' => null, 'body' => '', 'footer' => null, 'buttons' => []])
<div {{ $attributes->merge(['class' => 'max-w-[92%] rounded-lg rounded-tl-none bg-white shadow text-[13px] text-slate-800 overflow-hidden']) }}>
    @if ($headerFormat && $headerFormat !== 'TEXT')
        <div class="h-28 bg-slate-200 grid place-items-center text-slate-500 text-xs"><x-icon name="image" class="w-8 h-8" /> {{ $headerFormat }}</div>
    @endif
    <div class="px-2.5 pt-2 pb-1">
        @if ($header)<div class="font-semibold mb-1" data-wa="header">{{ $header }}</div>@endif
        <div class="whitespace-pre-line break-words" data-wa="body">{{ $body }}</div>
        @if ($footer)<div class="text-[11px] text-slate-500 mt-1" data-wa="footer">{{ $footer }}</div>@endif
        <div class="text-right text-[10px] text-slate-400">{{ now()->format('H:i') }}</div>
    </div>
    @foreach ($buttons as $b)
        <div class="border-t text-center py-1.5 text-[13px] text-sky-600 font-medium">{{ $b['text'] ?? '' }}</div>
    @endforeach
</div>
