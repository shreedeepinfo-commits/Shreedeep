<?php

namespace App\Http\Controllers;

use App\Jobs\ImportContacts;
use App\Jobs\StartCampaign;
use App\Models\Campaign;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\Campaigns\CampaignLauncher;
use App\Support\CurrentWorkspace;
use App\Support\PastedNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public const TABS = ['all' => 'All', 'running' => 'Sending', 'completed' => 'Sent', 'scheduled' => 'Scheduled', 'paused' => 'Paused', 'cancelled' => 'Cancelled'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $tabCounts = Campaign::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $tabCounts['all'] = $tabCounts->sum();

        $campaigns = Campaign::with(['template', 'whatsappAccount'])
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->when($request->query('q'), fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->latest()->paginate(20)->withQueryString();

        $monthStart = now()->startOfMonth();
        $kpis = [
            'total' => Campaign::count(),
            'thisMonth' => Campaign::where('created_at', '>=', $monthStart)->count(),
            'sentThisMonth' => Message::where('direction', 'out')->whereNotNull('campaign_id')->where('created_at', '>=', $monthStart)->whereNotIn('status', ['queued', 'failed'])->count(),
            'scheduled' => Campaign::where('status', 'scheduled')->count(),
            'attention' => Campaign::where('status', 'paused')->count()
                + Campaign::whereHas('messages', fn ($q) => $q->where('status', 'failed'))->whereNotIn('status', ['paused'])->count(),
        ];
        $counts = Message::whereIn('campaign_id', $campaigns->pluck('id'))
            ->selectRaw('campaign_id, status, count(*) as c')->groupBy('campaign_id', 'status')->get()
            ->groupBy('campaign_id')->map(fn ($rows) => $rows->pluck('c', 'status'));

        return view('campaigns.index', compact('campaigns', 'counts', 'tab', 'tabCounts', 'kpis'));
    }

    public function create()
    {
        $templates = MessageTemplate::approved()->orderBy('name')->get();

        return view('campaigns.create', [
            'accounts' => WhatsappAccount::where('status', 'connected')->get(),
            'templates' => $templates,
            'templateMeta' => $templates->mapWithKeys(fn ($t) => [$t->id => [
                'body' => $t->bodyText(),
                'vars' => $t->bodyVariableCount(),
                'header' => $t->headerFormat(),
                'headerText' => $t->component('HEADER')['text'] ?? null,
                'headerVars' => $t->headerVariableCount(),
                'footer' => $t->component('FOOTER')['text'] ?? null,
                'buttons' => array_column($t->component('BUTTONS')['buttons'] ?? [], 'text'),
            ]]),
            'groups' => ContactGroup::withCount(['contacts' => fn ($q) => $q->whereNull('opted_out_at')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CampaignLauncher $launcher, CurrentWorkspace $current)
    {
        $ws = $current->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message_template_id' => ['required', Rule::exists('message_templates', 'id')->where('workspace_id', $ws)->where('status', 'APPROVED')],
            'audience' => ['required', 'in:all,groups,paste,csv'],
            'numbers' => ['required_if:audience,paste', 'nullable', 'string', 'max:2000000'],
            'file' => ['required_if:audience,csv', 'nullable', 'file', 'mimes:csv,txt', 'max:20480'],
            'default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'consent' => ['required_if:audience,paste,csv', 'nullable', 'in:1,on,yes,true'],
            'groups' => ['required_if:audience,groups', 'array'],
            'groups.*' => [Rule::exists('contact_groups', 'id')->where('workspace_id', $ws)],
            'variables.body' => ['nullable', 'array'],
            'variables.body.*.type' => ['required', 'in:name,phone,email,field,text'],
            'variables.body.*.value' => ['nullable', 'string', 'max:1000'],
            'variables.body.*.fallback' => ['nullable', 'string', 'max:255'],
            'variables.header.type' => ['nullable', 'in:name,phone,email,field,text'],
            'variables.header.value' => ['nullable', 'string', 'max:60'],
            'header_media_url' => ['nullable', 'url', 'max:2000'],
            'send' => ['required', 'in:now,later'],
            'scheduled_at' => ['required_if:send,later', 'nullable', 'date', 'after:now'],
        ], ['consent.required_if' => 'Please confirm these people agreed to receive WhatsApp messages from you.']);

        $template = MessageTemplate::findOrFail($data['message_template_id']);
        if (in_array($template->headerFormat(), ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && empty($data['header_media_url'])) {
            return back()->withErrors(['header_media_url' => 'This template needs a header '.strtolower($template->headerFormat()).' link.'])->withInput();
        }

        // Paste / CSV audience: build a list named after the campaign first (bulk import, 20k+ is fine).
        if (in_array($data['audience'], ['paste', 'csv'], true)) {
            $group = ContactGroup::firstOrCreate(['name' => $data['name'].' list']);
            $import = ContactImport::create([
                'contact_group_id' => $group->id,
                'path' => $data['audience'] === 'paste' ? PastedNumbers::toCsv($data['numbers']) : $request->file('file')->store('imports'),
                'original_name' => $data['audience'] === 'paste' ? 'Pasted for campaign: '.$data['name'] : $request->file('file')->getClientOriginalName(),
                'default_country_code' => ltrim($data['default_country_code'] ?? '91', '+'),
                'opt_in_source' => 'campaign upload',
            ]);
            ImportContacts::dispatchSync($import->id);
            $data['audience'] = 'groups';
            $data['groups'] = [$group->id];
        }

        $campaign = DB::transaction(function () use ($data, $template, $launcher) {
            $campaign = Campaign::create([
                'name' => $data['name'],
                'whatsapp_account_id' => $template->whatsapp_account_id,
                'message_template_id' => $template->id,
                'audience' => ['all' => $data['audience'] === 'all', 'groups' => array_map('intval', $data['groups'] ?? [])],
                'variables' => $data['variables'] ?? [],
                'header_media_url' => $data['header_media_url'] ?? null,
                'scheduled_at' => $data['send'] === 'later' ? $data['scheduled_at'] : null,
                'status' => $data['send'] === 'later' ? 'scheduled' : 'running',
            ]);
            $launcher->prepare($campaign);

            return $campaign;
        });

        if ($campaign->total === 0) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);

            return redirect()->route('campaigns.show', $campaign)->withErrors(['audience' => 'No subscribed contacts in this audience.']);
        }

        if ($campaign->status === 'running') {
            StartCampaign::dispatch($campaign->id);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', $campaign->status === 'scheduled'
            ? "Campaign scheduled for {$campaign->scheduled_at->format('d M Y, h:i A')} to {$campaign->total} contacts."
            : "Campaign started: sending to {$campaign->total} contacts.");
    }

    public function show(Request $request, Campaign $campaign)
    {
        $messages = $campaign->messages()->with('contact')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id')->paginate(50)->withQueryString();
        $errors = $campaign->messages()->where('status', 'failed')
            ->selectRaw('error_message, count(*) as c')->groupBy('error_message')->orderByDesc('c')->limit(5)->get();

        return view('campaigns.show', ['campaign' => $campaign->load('template', 'whatsappAccount'), 'stats' => $campaign->stats(), 'messages' => $messages, 'failures' => $errors]);
    }

    public function pause(Campaign $campaign)
    {
        abort_unless(in_array($campaign->status, ['running', 'scheduled'], true), 422);
        $campaign->update(['status' => 'paused']);

        return back()->with('status', 'Campaign paused.');
    }

    public function resume(Campaign $campaign, CampaignLauncher $launcher)
    {
        abort_unless($campaign->status === 'paused', 422);
        $campaign->update(['status' => 'running']);
        StartCampaign::dispatch($campaign->id);

        return back()->with('status', 'Campaign resumed.');
    }

    public function cancel(Campaign $campaign)
    {
        abort_if(in_array($campaign->status, ['completed', 'cancelled'], true), 422);
        $campaign->update(['status' => 'cancelled', 'completed_at' => now()]);
        $campaign->messages()->where('status', 'queued')->update(['status' => 'failed', 'error_message' => 'Campaign cancelled', 'failed_at' => now()]);

        return back()->with('status', 'Campaign cancelled.');
    }

    public function export(Campaign $campaign): StreamedResponse
    {
        return response()->streamDownload(function () use ($campaign) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['phone', 'name', 'status', 'sent_at', 'delivered_at', 'read_at', 'error'], escape: '\\');
            $campaign->messages()->with('contact:id,name')->orderBy('id')->chunk(1000, function ($messages) use ($out) {
                foreach ($messages as $m) {
                    fputcsv($out, [$m->phone, $m->contact?->name, $m->status, $m->sent_at, $m->delivered_at, $m->read_at, $m->error_message], escape: '\\');
                }
            });
            fclose($out);
        }, 'campaign-'.$campaign->id.'-report.csv', ['Content-Type' => 'text/csv']);
    }
}
