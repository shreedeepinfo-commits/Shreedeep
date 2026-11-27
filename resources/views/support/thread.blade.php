{{-- Ticket conversation; $staffView = true in the super admin panel. --}}
<div class="space-y-4">
    @foreach ($ticket->replies as $r)
        @php $mine = $staffView ? $r->from_support : ! $r->from_support; @endphp
        <div class="flex {{ $mine ? 'justify-end' : '' }}">
            <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $r->from_support ? 'bg-brand-50 border border-brand-100' : 'bg-white border border-slate-200' }}">
                <div class="text-xs font-semibold {{ $r->from_support ? 'text-brand-700' : 'text-slate-700' }}">
                    {{ $r->from_support ? config('brand.name').' Support' : ($r->user?->name ?? 'Customer') }}
                    <span class="font-normal text-slate-400">· {{ $r->created_at->format('d M, h:i A') }}</span>
                </div>
                <div class="text-sm whitespace-pre-line break-words mt-1">{{ $r->body }}</div>
            </div>
        </div>
    @endforeach
</div>
