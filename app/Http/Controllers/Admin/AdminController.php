<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Platform owner panel: every customer, their plan and money. Tenant scopes do not apply here (no current workspace). */
class AdminController extends Controller
{
    public const STATUSES = [
        'active' => ['Paid', 'bg-emerald-50 text-emerald-700'],
        'trial' => ['Trial', 'bg-sky-50 text-sky-700'],
        'expired' => ['Expired', 'bg-amber-50 text-amber-700'],
        'suspended' => ['Suspended', 'bg-red-50 text-red-700'],
    ];

    public function dashboard()
    {
        $workspaces = Workspace::with('owner')->get();
        $byStatus = $workspaces->groupBy(fn (Workspace $w) => $w->status());
        $paid = Payment::withoutGlobalScopes()->where('status', 'paid');

        return view('admin.dashboard', [
            'counts' => collect(self::STATUSES)->map(fn ($s, $k) => ($byStatus[$k] ?? collect())->count()),
            'total' => $workspaces->count(),
            'mrr' => $workspaces->sum(fn (Workspace $w) => $w->monthlyValue()),
            'revenueMonth' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'revenueTotal' => (clone $paid)->sum('amount'),
            'signups30' => $workspaces->where('created_at', '>=', now()->subDays(30))->count(),
            'numbers' => WhatsappAccount::withoutGlobalScopes()->count(),
            'messagesMonth' => Message::withoutGlobalScopes()->where('direction', 'out')->where('created_at', '>=', now()->startOfMonth())->count(),
            'campaignsMonth' => Campaign::withoutGlobalScopes()->where('created_at', '>=', now()->startOfMonth())->count(),
            'plans' => $workspaces->filter->hasActivePlan()->countBy('plan'),
            'recentSignups' => $workspaces->sortByDesc('id')->take(6),
            'recentPayments' => Payment::withoutGlobalScopes()->with('workspace')->where('status', 'paid')->latest('paid_at')->limit(6)->get(),
            'endingSoon' => $workspaces->filter(fn (Workspace $w) => ! $w->isSuspended() && $w->daysLeft() > 0 && $w->daysLeft() <= 7)->sortBy(fn ($w) => $w->accessEndsAt())->take(6),
            'revenueChart' => collect(range(5, 0))->map(fn ($i) => [
                now()->subMonths($i)->format('M'),
                (float) Payment::withoutGlobalScopes()->where('status', 'paid')
                    ->whereBetween('paid_at', [now()->subMonths($i)->startOfMonth(), now()->subMonths($i)->endOfMonth()])->sum('amount'),
            ]),
        ]);
    }

    public function customers(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : null;

        $workspaces = Workspace::with('owner')->withCount('users')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('billing_gstin', 'like', "%{$q}%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))))
            ->latest('id')->get()
            ->when($status, fn ($c) => $c->filter(fn (Workspace $w) => $w->status() === $status));

        return view('admin.customers', ['workspaces' => $workspaces, 'q' => $q, 'status' => $status]);
    }

    public function customer(Workspace $workspace)
    {
        return view('admin.customer', [
            'w' => $workspace->load('owner'),
            'members' => $workspace->users()->orderBy('users.id')->get(),
            'numbers' => WhatsappAccount::withoutGlobalScopes()->where('workspace_id', $workspace->id)->get(),
            'usage' => collect(config('billing.limit_labels'))->map(fn ($label, $key) => ['label' => $label, 'used' => $workspace->usage($key), 'limit' => $workspace->limit($key)]),
            'payments' => Payment::withoutGlobalScopes()->with('invoice')->where('workspace_id', $workspace->id)->latest('id')->get(),
            'stats' => [
                'messages' => Message::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('direction', 'out')->count(),
                'campaigns' => Campaign::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count(),
                'lastActive' => Message::withoutGlobalScopes()->where('workspace_id', $workspace->id)->max('created_at'),
            ],
        ]);
    }

    /** Give a plan by hand (offline payment, free months) or extend the trial. No invoice is made. */
    public function plan(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'action' => ['required', 'in:plan,trial'],
            'plan' => ['required_if:action,plan', 'nullable', Rule::in(array_keys(config('billing.plans')))],
            'cycle' => ['nullable', Rule::in(array_keys(config('billing.cycles')))],
            'ends_at' => ['required', 'date', 'after:today'],
        ]);
        $ends = \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay();

        if ($data['action'] === 'trial') {
            $workspace->forceFill(['trial_ends_at' => $ends])->save();
            $msg = 'Trial extended to '.$ends->format('d M Y').'.';
        } else {
            $workspace->forceFill(['plan' => $data['plan'], 'plan_cycle' => $data['cycle'] ?? 'monthly', 'plan_ends_at' => $ends])->save();
            $msg = config("billing.plans.{$data['plan']}.name").' plan active until '.$ends->format('d M Y').'.';
        }

        return back()->with('status', $msg);
    }

    public function suspend(Workspace $workspace)
    {
        $workspace->forceFill(['suspended_at' => $workspace->suspended_at ? null : now()])->save();

        return back()->with('status', $workspace->name.($workspace->suspended_at ? ' is suspended. Its team cannot use the panel.' : ' is active again.'));
    }

    public function note(Request $request, Workspace $workspace)
    {
        $workspace->forceFill(['admin_note' => $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']])['admin_note']])->save();

        return back()->with('status', 'Note saved.');
    }

    /** Open the customer's panel as its owner, to help them. */
    public function impersonate(Request $request, Workspace $workspace)
    {
        $owner = $workspace->owner;
        abort_if(! $owner || $owner->is_super_admin, 422, 'This customer has no owner account to open.');
        $adminId = $request->user()->id;

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $adminId);
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request)
    {
        $admin = User::find($request->session()->pull('impersonator_id'));
        abort_unless($admin?->is_super_admin, 403);

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.customers')->with('status', 'Back in the super admin panel.');
    }

    public function payments(Request $request)
    {
        $status = in_array($request->query('status'), ['paid', 'failed', 'created'], true) ? $request->query('status') : null;

        return view('admin.payments', [
            'payments' => Payment::withoutGlobalScopes()->with(['workspace', 'invoice'])
                ->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(50)->withQueryString(),
            'status' => $status,
            'totals' => Payment::withoutGlobalScopes()->where('status', 'paid')->selectRaw('sum(amount) as amount, sum(tax) as tax, sum(total) as total, count(*) as n')->first(),
        ]);
    }

    public function invoice(Invoice $invoice)
    {
        return view('subscription.invoice', ['invoice' => $invoice, 'back' => route('admin.payments')]);
    }

    public function plans()
    {
        $workspaces = Workspace::all();

        return view('admin.plans', [
            'plans' => config('billing.plans'),
            'cycles' => config('billing.cycles'),
            'customers' => $workspaces->filter->hasActivePlan()->countBy('plan'),
        ]);
    }
}
