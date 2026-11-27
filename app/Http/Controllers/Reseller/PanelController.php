<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Services\Resellers\Provisioning;
use App\Services\Resellers\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** The reseller's own panel: customers, sub-resellers, wallet and white-label settings. */
class PanelController extends Controller
{
    private function me(): Reseller
    {
        return app('reseller');
    }

    private function customer(Workspace $workspace): Workspace
    {
        abort_unless($workspace->reseller_id === $this->me()->id, 404);

        return $workspace;
    }

    private function child(Reseller $child): Reseller
    {
        abort_unless($child->parent_id === $this->me()->id, 404);

        return $child;
    }

    public function dashboard()
    {
        $me = $this->me();
        $customers = $me->workspaces()->get();

        return view('reseller.dashboard', [
            'me' => $me,
            'stats' => [
                'customers' => $customers->count(),
                'active' => $customers->filter->hasActivePlan()->count(),
                'trial' => $customers->filter->onTrial()->count(),
                'expired' => $customers->filter->isExpired()->count(),
            ],
            'expiring' => $customers->filter(fn ($w) => $w->accessEndsAt()?->between(now(), now()->addDays(7)))->sortBy(fn ($w) => $w->accessEndsAt())->values(),
            'monthlyValue' => $customers->filter->hasActivePlan()->sum(fn ($w) => $me->price($w->plan)),
            'monthlyCost' => $customers->filter->hasActivePlan()->sum(fn ($w) => $me->cost($w->plan)),
            'transactions' => $me->transactions()->latest('id')->limit(8)->get(),
            'children' => $me->children()->count(),
        ]);
    }

    public function customers(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('reseller.customers', [
            'me' => $this->me(),
            'customers' => $this->me()->workspaces()->with('owner')
                ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%$q%"))))
                ->latest('id')->paginate(30)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^\+?\d{10,15}$/'],
            'plan' => ['nullable', Rule::in(array_keys(config('billing.plans')))],
            'cycle' => ['required_with:plan', 'nullable', Rule::in(array_keys(config('billing.cycles')))],
        ]);
        [$workspace, $password] = Provisioning::createCustomer($this->me(), $data, $request->user());

        return redirect()->route('reseller.customers')->with('credentials', ['name' => $workspace->name, 'email' => $data['email'], 'password' => $password, 'url' => $this->me()->url().'/login'])
            ->with('status', $workspace->name.' is ready.'.(! empty($data['plan']) ? ' Plan activated from your wallet.' : ' Trial started.'));
    }

    public function activate(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('billing.plans')))],
            'cycle' => ['required', Rule::in(array_keys(config('billing.cycles')))],
        ]);
        $tx = Wallet::activate($this->me(), $this->customer($workspace), $data['plan'], $data['cycle'], $request->user());

        return back()->with('status', $tx->description.'. '.$tx->amountLabel().' from your wallet.');
    }

    public function toggle(Request $request, Workspace $workspace)
    {
        $workspace = $this->customer($workspace);
        $field = $request->validate(['field' => ['required', 'in:auto_renew,suspended']])['field'];
        if ($field === 'auto_renew') {
            $workspace->forceFill(['auto_renew' => ! $workspace->auto_renew])->save();
        } else {
            $workspace->forceFill(['suspended_at' => $workspace->suspended_at ? null : now()])->save();
        }

        return back()->with('status', $workspace->name.' updated.');
    }

    public function impersonate(Request $request, Workspace $workspace)
    {
        $owner = $this->customer($workspace)->owner;
        abort_if(! $owner || $owner->is_super_admin || $owner->reseller_id, 422, 'This customer has no owner login to open.');
        $me = $request->user()->id;

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put(['impersonator_id' => $me, 'impersonator_label' => 'Reseller panel']);
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard');
    }

    public function resellers()
    {
        abort_unless($this->me()->can_create_resellers, 403);

        return view('reseller.resellers', [
            'me' => $this->me(),
            'children' => $this->me()->children()->withCount('workspaces')->with('user')->latest('id')->get(),
        ]);
    }

    public function storeReseller(Request $request)
    {
        $me = $this->me();
        abort_unless($me->can_create_resellers, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => Provisioning::slugRules(),
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^\+?\d{10,15}$/'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:'.config('reseller.max_trial_days')],
            'can_create_resellers' => ['nullable', 'boolean'],
        ] + Provisioning::costRules($me), Provisioning::costMessages($me));
        [$child, $password] = Provisioning::createReseller($me, $data);

        return redirect()->route('reseller.resellers')->with('credentials', ['name' => $child->name, 'email' => $data['email'], 'password' => $password, 'url' => $child->url().'/login'])
            ->with('status', $child->name.' can now log in and add customers.');
    }

    public function updateReseller(Request $request, Reseller $child)
    {
        $me = $this->me();
        $child = $this->child($child);
        $data = $request->validate(['can_create_resellers' => ['nullable', 'boolean']] + Provisioning::costRules($me), Provisioning::costMessages($me));
        $child->update(['costs' => array_map('floatval', $data['costs']), 'can_create_resellers' => $request->boolean('can_create_resellers')]);

        return back()->with('status', $child->name.' updated.');
    }

    public function transfer(Request $request, Reseller $child)
    {
        $child = $this->child($child);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:10000000']]);
        Wallet::transfer($this->me(), $child, Wallet::paise($data['amount']), $request->user());

        return back()->with('status', '₹'.number_format($data['amount'], 2).' sent to '.$child->name.'.');
    }

    public function suspendReseller(Reseller $child)
    {
        $child = $this->child($child);
        $child->forceFill(['suspended_at' => $child->suspended_at ? null : now()])->save();

        return back()->with('status', $child->name.($child->suspended_at ? ' suspended.' : ' is active again.'));
    }

    public function wallet()
    {
        $me = $this->me();

        return view('reseller.wallet', [
            'me' => $me,
            'transactions' => $me->transactions()->with('workspace')->latest('id')->paginate(50),
            'parent' => $me->parent,
        ]);
    }

    public function settings()
    {
        return view('reseller.settings', ['me' => $this->me()]);
    }

    public function updateSettings(Request $request)
    {
        $me = $this->me();
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'product_name' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url:https', 'max:500'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_domain' => ['nullable', 'string', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', Rule::unique('resellers', 'custom_domain')->ignore($me)],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'regex:/^\+?\d{10,15}$/'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:'.config('reseller.max_trial_days')],
            'low_balance_alert' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'prices' => ['required', 'array'],
        ];
        foreach (array_keys(config('billing.plans')) as $plan) {
            $rules["prices.$plan"] = ['required', 'numeric', 'min:0', 'max:1000000'];
        }
        $data = $request->validate($rules);
        if (isset($data['custom_domain'])) {
            $data['custom_domain'] = strtolower($data['custom_domain']);
        }
        Provisioning::checkPrices($me, $data['prices']);
        $data['prices'] = array_map('floatval', $data['prices']);
        $data['low_balance_alert'] = Wallet::paise($data['low_balance_alert']);
        $me->update($data);

        return back()->with('status', 'Settings saved. Your customers now see this brand.');
    }
}
