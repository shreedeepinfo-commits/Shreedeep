@props(['name' => 'Your Business', 'number' => null])
{{-- WhatsApp-style phone mockup. Put the message bubble(s) in the slot. --}}
<div class="mx-auto w-[290px] rounded-[2.5rem] bg-slate-900 p-3 shadow-2xl">
    <div class="rounded-[2rem] overflow-hidden bg-[#efeae2]">
        <div class="bg-[#075e54] text-white px-4 pt-3 pb-2">
            <div class="flex justify-between text-[10px] opacity-80"><span>{{ now()->format('H:i') }}</span><span>●●● ▮</span></div>
            <div class="flex items-center gap-2 mt-2">
                <span class="text-lg leading-none">‹</span>
                <span class="w-8 h-8 rounded-full bg-white/80 grid place-items-center text-[#075e54] text-xs font-bold">{{ mb_substr($name, 0, 1) }}</span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold">{{ $name }}</span>
                    @if ($number)<span class="block text-[10px] opacity-80">{{ $number }}</span>@endif
                </span>
            </div>
        </div>
        <div class="h-[420px] overflow-y-auto p-3 space-y-2" style="background-image: radial-gradient(rgba(0,0,0,.035) 1px, transparent 1px); background-size: 14px 14px;">
            {{ $slot }}
        </div>
    </div>
</div>
