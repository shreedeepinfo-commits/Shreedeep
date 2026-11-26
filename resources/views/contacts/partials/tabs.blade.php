@php
    $crmTabs = [
        ['Contacts', 'contacts.index', 'users'],
        ['Pipeline', 'crm.pipeline', 'chart'],
        ['Follow-ups', 'crm.followups', 'calendar'],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach ($crmTabs as [$label, $route, $icon])
        <a href="{{ route($route) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition {{ request()->routeIs($route) ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:border-brand-300' }}">
            <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
        </a>
    @endforeach
</div>
