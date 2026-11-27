{{-- Monthly plan cost inputs. $values: plan => rupees, $min: plan => minimum (optional) --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    @foreach (config('billing.plans') as $key => $p)
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">{{ $p['name'] }} cost / month (₹)</label>
            <input type="number" step="1" name="costs[{{ $key }}]" value="{{ old("costs.$key", $values[$key] ?? '') }}" min="{{ $min[$key] ?? 0 }}" required
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none">
            <div class="text-[11px] text-slate-400 mt-0.5">List price ₹{{ \App\Support\Num::indian($p['price']) }}@isset($min[$key]) · your cost ₹{{ \App\Support\Num::indian($min[$key]) }}@endisset</div>
        </div>
    @endforeach
</div>
