{{-- New reseller form. $action, $parent (?Reseller) --}}
@php
    $in = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $lb = 'block text-xs font-semibold text-slate-500 mb-1';
    $pct = config('reseller.default_cost_percent') / 100;
    $defaults = collect(config('billing.plans'))->map(fn ($p, $k) => $parent ? (int) ceil($parent->cost($k) * 1.15) : (int) round($p['price'] * $pct))->all();
@endphp
<form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
    @csrf
    <div class="font-semibold">Add a reseller</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="{{ $lb }}">Brand name</label><input name="name" value="{{ old('name') }}" required maxlength="100" placeholder="Sharma Digital" class="{{ $in }}" oninput="if(!this.form.slug.dataset.touched){this.form.slug.value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)}"></div>
        <div><label class="{{ $lb }}">Panel address</label>
            <div class="flex items-center gap-1"><input name="slug" value="{{ old('slug') }}" required maxlength="40" placeholder="sharma" class="{{ $in }}" oninput="this.dataset.touched=1"><span class="text-xs text-slate-500 whitespace-nowrap">.{{ config('reseller.base_domain') }}</span></div>
        </div>
        <div><label class="{{ $lb }}">Contact person</label><input name="contact_name" value="{{ old('contact_name') }}" required maxlength="255" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Login email</label><input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Phone (optional)</label><input name="phone" value="{{ old('phone') }}" maxlength="16" placeholder="919876543210" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Free trial for their customers (days)</label><input type="number" name="trial_days" value="{{ old('trial_days', 7) }}" min="0" max="{{ config('reseller.max_trial_days') }}" class="{{ $in }}"></div>
    </div>
    <div>
        <div class="text-sm font-medium mb-2">What this reseller pays you (rental per customer)</div>
        @include('partials.reseller-costs', ['values' => $defaults, 'min' => $parent ? collect(config('billing.plans'))->map(fn ($p, $k) => $parent->cost($k))->all() : []])
    </div>
    @if (! $parent)
        <div><label class="{{ $lb }}">Opening wallet balance (₹, optional)</label><input type="number" name="opening_balance" value="{{ old('opening_balance') }}" min="0" step="1" class="{{ $in }}"></div>
    @endif
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_create_resellers" value="1" class="rounded text-brand-600" @checked(old('can_create_resellers'))> Can create their own sub-resellers</label>
    <x-btn icon="plus">Create reseller</x-btn>
    <p class="text-xs text-slate-500">A login is created right away. Their customers see their brand, not yours. WhatsApp message charges are always paid by each customer directly to Meta.</p>
</form>
