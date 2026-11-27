@extends('layouts.admin', ['title' => 'Customers'])
@php use App\Http\Controllers\Admin\AdminController; @endphp

@section('content')
<x-page-header title="Users / Customers" subtitle="Every business using the WhatsApp panel. Add a user here to give them their own full WhatsApp panel." />

<details class="mb-5 bg-white rounded-2xl border-2 border-brand-200 shadow-sm p-5" @if ($errors->any()) open @endif>
    <summary class="cursor-pointer font-semibold text-brand-700 flex items-center gap-2"><x-icon name="user-plus" class="w-5 h-5" /> Add user / customer</summary>
    <form method="POST" action="{{ route('admin.customers.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
        @csrf
        <input name="company" value="{{ old('company') }}" required maxlength="255" placeholder="Business name" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
        <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Owner name" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="Login email" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
        <input name="phone" value="{{ old('phone') }}" maxlength="16" placeholder="Phone (optional)" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
        <select name="plan" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
            <option value="">{{ config('billing.trial_days') }}-day free trial</option>
            @foreach (config('billing.plans') as $k => $p)<option value="{{ $k }}" @selected(old('plan') === $k)>{{ $p['name'] }} plan (paid outside)</option>@endforeach
        </select>
        <select name="months" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
            @foreach ([1, 3, 6, 12] as $m)<option value="{{ $m }}">{{ $m }} month{{ $m > 1 ? 's' : '' }}</option>@endforeach
        </select>
        <div class="md:col-span-3 flex items-center gap-3"><x-btn icon="user-plus">Create user</x-btn><span class="text-xs text-slate-500">Login details are shown once after creating. The user gets the full WhatsApp panel and connects their own number.</span></div>
    </form>
</details>

<form class="flex flex-wrap gap-2 mb-4">
    <input name="q" value="{{ $q }}" placeholder="Search business, owner, email or GSTIN…" class="flex-1 min-w-[16rem] border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
    <select name="status" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach (AdminController::STATUSES as $k => [$label])<option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>@endforeach
    </select>
    <x-btn icon="search">Search</x-btn>
</form>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-5 py-3">Business</th><th class="text-left px-3 py-3">Owner</th><th class="text-left px-3 py-3">Plan</th><th class="text-left px-3 py-3">Status</th><th class="text-left px-3 py-3">Ends</th><th class="text-right px-3 py-3">Team</th><th class="text-left px-5 py-3">Joined</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($workspaces as $w)
                    @php [$label, $cls] = AdminController::STATUSES[$w->status()]; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><a href="{{ route('admin.customers.show', $w) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $w->name }}</a></td>
                        <td class="px-3 py-3">{{ $w->owner?->name }}<div class="text-xs text-slate-500">{{ $w->owner?->email }}</div></td>
                        <td class="px-3 py-3">{{ $w->planName() }}</td>
                        <td class="px-3 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $cls }}">{{ $label }}</span></td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $w->accessEndsAt()?->format('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-3 text-right">{{ $w->users_count }}</td>
                        <td class="px-5 py-3 whitespace-nowrap text-slate-500">{{ $w->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
