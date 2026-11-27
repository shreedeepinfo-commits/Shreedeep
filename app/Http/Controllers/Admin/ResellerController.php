<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Resellers\Provisioning;
use App\Services\Resellers\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Super admin: create resellers, set their costs, recharge their wallets. */
class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('admin.resellers', [
            'resellers' => Reseller::with(['parent', 'user'])->withCount(['workspaces', 'children'])
                ->when($q !== '', fn ($r) => $r->where(fn ($r) => $r->where('name', 'like', "%$q%")->orWhere('slug', 'like', "%$q%")))
                ->orderByRaw('parent_id is not null')->latest('id')->paginate(50)->withQueryString(),
            'q' => $q,
            'totals' => ['balance' => Reseller::sum('balance'), 'count' => Reseller::count(), 'customers' => \App\Models\Workspace::whereNotNull('reseller_id')->count()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => Provisioning::slugRules(),
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^\+?\d{10,15}$/'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:'.config('reseller.max_trial_days')],
            'can_create_resellers' => ['nullable', 'boolean'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ] + Provisioning::costRules(null));
        [$reseller, $password] = Provisioning::createReseller(null, $data);
        if (! empty($data['opening_balance'])) {
            Wallet::credit($reseller, Wallet::paise($data['opening_balance']), 'recharge', 'Opening balance', ['user_id' => $request->user()->id]);
        }

        return redirect()->route('admin.resellers.show', $reseller)
            ->with('credentials', ['name' => $reseller->name, 'email' => $data['email'], 'password' => $password, 'url' => $reseller->url().'/login'])
            ->with('status', 'Reseller created. Share the login details below.');
    }

    public function show(Reseller $reseller)
    {
        return view('admin.reseller', [
            'reseller' => $reseller->load(['parent', 'user']),
            'customers' => $reseller->workspaces()->with('owner')->latest('id')->limit(100)->get(),
            'children' => $reseller->children()->withCount('workspaces')->get(),
            'transactions' => $reseller->transactions()->with('workspace')->latest('id')->limit(50)->get(),
        ]);
    }

    public function wallet(Request $request, Reseller $reseller)
    {
        $data = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'reason' => ['required', 'in:recharge,adjustment,refund'],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:150'],
        ]);
        $paise = Wallet::paise($data['amount']);
        $description = ($data['note'] ?? null) ?: \App\Models\WalletTransaction::REASONS[$data['reason']].' by Super Admin';
        $data['type'] === 'credit'
            ? Wallet::credit($reseller, $paise, $data['reason'], $description, ['user_id' => $request->user()->id])
            : Wallet::debit($reseller, $paise, $data['reason'], $description, ['user_id' => $request->user()->id]);

        return back()->with('status', 'Wallet updated. New balance '.$reseller->fresh()->balanceLabel().'.');
    }

    public function update(Request $request, Reseller $reseller)
    {
        $data = $request->validate(['can_create_resellers' => ['nullable', 'boolean']] + Provisioning::costRules($reseller->parent), Provisioning::costMessages($reseller->parent));
        $reseller->update(['costs' => array_map('floatval', $data['costs']), 'can_create_resellers' => $request->boolean('can_create_resellers')]);

        return back()->with('status', 'Reseller updated.');
    }

    public function suspend(Reseller $reseller)
    {
        $reseller->forceFill(['suspended_at' => $reseller->suspended_at ? null : now()])->save();

        return back()->with('status', $reseller->name.($reseller->suspended_at ? ' suspended. Its customers keep working until their plans end.' : ' is active again.'));
    }

    public function impersonate(Request $request, Reseller $reseller)
    {
        $adminId = $request->user()->id;
        Auth::login($reseller->user);
        $request->session()->regenerate();
        $request->session()->put(['impersonator_id' => $adminId, 'impersonator_label' => 'Super Admin']);

        return redirect()->route('reseller.dashboard');
    }
}
