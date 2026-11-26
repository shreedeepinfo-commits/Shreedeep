<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public const TABS = ['all' => 'All', 'APPROVED' => 'Approved', 'PENDING' => 'Pending', 'REJECTED' => 'Rejected'];

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'all';
        $counts = MessageTemplate::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $templates = MessageTemplate::with('whatsappAccount')
            ->when($status === 'REJECTED', fn ($q) => $q->whereIn('status', ['REJECTED', 'PAUSED', 'DISABLED']))
            ->when(! in_array($status, ['all', 'REJECTED'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('components', 'like', "%{$t}%")))
            ->latest('updated_at')->get();

        $selected = $templates->firstWhere('id', (int) $request->query('t')) ?? $templates->first();

        return view('templates.index', [
            'templates' => $templates,
            'selected' => $selected,
            'status' => $status,
            'accounts' => WhatsappAccount::all(),
            'kpis' => [
                'approved' => (int) ($counts['APPROVED'] ?? 0),
                'pending' => (int) ($counts['PENDING'] ?? 0) + (int) ($counts['IN_APPEAL'] ?? 0),
                'rejected' => (int) ($counts['REJECTED'] ?? 0) + (int) ($counts['PAUSED'] ?? 0) + (int) ($counts['DISABLED'] ?? 0),
                'total' => (int) $counts->sum(),
            ],
        ]);
    }

    public function sync(TemplateService $service)
    {
        $total = 0;
        $errors = [];
        foreach (WhatsappAccount::all() as $account) {
            try {
                $total += $service->sync($account);
            } catch (CloudApiException $e) {
                $errors[] = $account->label().': '.$e->getMessage();
            }
        }

        return redirect()->route('templates.index')
            ->with('status', "Synced {$total} templates from Meta.")
            ->withErrors($errors);
    }

    public function create()
    {
        return view('templates.create', [
            'accounts' => WhatsappAccount::all(),
            'exampleBody' => 'Hi {{1}}, our Diwali sale is live! Get {{2}} off today.',
        ]);
    }

    public function store(Request $request, TemplateService $service)
    {
        $data = $request->validate([
            'whatsapp_account_id' => ['required', Rule::exists('whatsapp_accounts', 'id')->where('workspace_id', app(\App\Support\CurrentWorkspace::class)->id())],
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:15'],
            'category' => ['required', 'in:MARKETING,UTILITY'],
            'header' => ['nullable', 'string', 'max:60'],
            'header_example' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'body_examples' => ['nullable', 'array'],
            'body_examples.*' => ['nullable', 'string', 'max:200'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.type' => ['nullable', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'buttons.*.text' => ['nullable', 'string', 'max:25'],
            'buttons.*.value' => ['nullable', 'string', 'max:2000'],
        ], ['name.regex' => 'Template name may only use lowercase letters, numbers and underscores.']);

        $account = WhatsappAccount::findOrFail($data['whatsapp_account_id']);

        try {
            $template = $service->create($account, $data);
        } catch (CloudApiException $e) {
            return back()->withErrors(['name' => 'Meta rejected the template: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('templates.index')->with('status', "Template {$template->name} submitted. Status: {$template->status}.");
    }

    public function destroy(MessageTemplate $template, TemplateService $service)
    {
        try {
            $service->delete($template);
        } catch (CloudApiException $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return back()->with('status', 'Template deleted.');
    }
}
