<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Reports: messages, campaigns, sources, failures, agents, contacts and best time to send. */
class AnalyticsController extends Controller
{
    public const RANGES = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];

    private const TIMEZONE = 'Asia/Kolkata';

    public function __construct(private CurrentWorkspace $current) {}

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->query('from'), self::TIMEZONE)->startOfDay();
            $to = Carbon::parse($request->query('to'), self::TIMEZONE)->endOfDay();
            if ($from->diffInDays($to) > 366) {
                $from = $to->copy()->subDays(366)->startOfDay();
            }

            return [$from->setTimezone(config('app.timezone')), $to->setTimezone(config('app.timezone')), 'custom'];
        }
        $days = array_key_exists((string) $request->query('days'), self::RANGES) ? (int) $request->query('days') : 30;

        return [now(self::TIMEZONE)->subDays($days - 1)->startOfDay()->setTimezone(config('app.timezone')), now(self::TIMEZONE)->endOfDay()->setTimezone(config('app.timezone')), (string) $days];
    }

    public function index(Request $request)
    {
        [$from, $to, $rangeKey] = $this->range($request);
        $ws = $this->current->id();
        $msgs = fn () => Message::where('messages.created_at', '>=', $from)->where('messages.created_at', '<=', $to);

        // Funnel for outgoing messages (statuses only move forward, so read ⊂ delivered ⊂ sent).
        $out = $msgs()->where('direction', 'out')->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $read = (int) ($out['read'] ?? 0);
        $delivered = (int) ($out['delivered'] ?? 0) + $read;
        $sent = (int) ($out['sent'] ?? 0) + $delivered;
        $failed = (int) ($out['failed'] ?? 0);
        $attempted = $sent + $failed;
        $pct = fn ($n, $of) => $of ? round($n * 100 / $of, 1) : 0;

        $inbound = $msgs()->where('direction', 'in')->count();
        $messagedContacts = $msgs()->where('direction', 'out')->distinct()->count('contact_id');
        $repliedContacts = $msgs()->where('direction', 'in')->distinct()->count('contact_id');

        // Estimated Meta charges for template messages that were delivered (by template category).
        $cost = $msgs()->where('messages.direction', 'out')->where('messages.type', 'template')->whereIn('messages.status', ['delivered', 'read'])
            ->leftJoin('campaigns', 'campaigns.id', '=', 'messages.campaign_id')
            ->leftJoin('message_templates', 'message_templates.id', '=', 'campaigns.message_template_id')
            ->selectRaw('message_templates.category as category, count(*) as c')->groupBy('message_templates.category')->get()
            ->sum(fn ($r) => $r->c * Campaign::rate($r->category ?? 'UTILITY'));

        return view('analytics.index', [
            'rangeKey' => $rangeKey,
            'from' => $from->copy()->setTimezone(self::TIMEZONE),
            'to' => $to->copy()->setTimezone(self::TIMEZONE),
            'kpi' => [
                'attempted' => $attempted, 'sent' => $sent, 'delivered' => $delivered, 'read' => $read, 'failed' => $failed,
                'deliveredPct' => $pct($delivered, $attempted), 'readPct' => $pct($read, $delivered), 'failedPct' => $pct($failed, $attempted),
                'inbound' => $inbound, 'conversations' => $repliedContacts,
                'replyRate' => $pct($repliedContacts, $messagedContacts),
                'cost' => round($cost, 2),
                'newContacts' => Contact::whereBetween('created_at', [$from, $to])->count(),
                'optOuts' => Contact::whereBetween('opted_out_at', [$from, $to])->count(),
            ],
            'daily' => $this->daily($from, $to),
            'sources' => $this->sources($msgs),
            'campaigns' => $this->campaigns($from, $to),
            'failures' => $msgs()->where('direction', 'out')->where('status', 'failed')
                ->selectRaw('error_code, error_message, count(*) as c')->groupBy('error_code', 'error_message')->orderByDesc('c')->limit(8)->get(),
            'agents' => $this->agents($ws, $from, $to),
            'hours' => $this->hours($msgs),
            'topTags' => Contact::whereNotNull('tags')->pluck('tags')->flatten()->filter()->countBy()->sortDesc()->take(8),
            'automations' => Automation::where('sent_count', '>', 0)->orderByDesc('sent_count')->limit(6)->get(),
        ]);
    }

    /** One row per day: outgoing, delivered, read, failed, incoming, new contacts. */
    private function daily(Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($d = $from->copy()->setTimezone(self::TIMEZONE)->startOfDay(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = ['date' => $d->toDateString(), 'out' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'in' => 0, 'contacts' => 0];
        }
        $local = fn ($ts) => Carbon::parse($ts)->setTimezone(self::TIMEZONE)->toDateString();

        Message::where('created_at', '>=', $from)->where('created_at', '<=', $to)->select(['id', 'direction', 'status', 'created_at'])
            ->chunkById(5000, function ($rows) use (&$days, $local) {
                foreach ($rows as $m) {
                    $k = $local($m->created_at);
                    if (! isset($days[$k])) {
                        continue;
                    }
                    if ($m->direction === 'in') {
                        $days[$k]['in']++;

                        continue;
                    }
                    $days[$k]['out']++;
                    $days[$k]['failed'] += $m->status === 'failed' ? 1 : 0;
                    $days[$k]['delivered'] += in_array($m->status, ['delivered', 'read'], true) ? 1 : 0;
                    $days[$k]['read'] += $m->status === 'read' ? 1 : 0;
                }
            });
        foreach (Contact::whereBetween('created_at', [$from, $to])->pluck('created_at') as $c) {
            $k = $local($c);
            if (isset($days[$k])) {
                $days[$k]['contacts']++;
            }
        }

        return array_values($days);
    }

    /** Where outgoing messages came from. */
    private function sources(\Closure $msgs): array
    {
        $rows = $msgs()->where('direction', 'out')->selectRaw("
            case
                when campaign_id is not null then 'campaign'
                when payload like '%\"source\":\"api\"%' then 'api'
                when payload like '%\"automation_id\"%' then 'automation'
                when user_id is not null then 'agent'
                else 'chatbot'
            end as source, count(*) as c")->groupBy('source')->pluck('c', 'source');

        return collect(['campaign' => 'Campaigns', 'agent' => 'Live chat (agents)', 'chatbot' => 'Chatbot flows', 'automation' => 'Automations', 'api' => 'API'])
            ->map(fn ($label, $k) => ['label' => $label, 'count' => (int) ($rows[$k] ?? 0)])->all();
    }

    private function campaigns(Carbon $from, Carbon $to)
    {
        $campaigns = Campaign::with('template')->whereBetween('created_at', [$from, $to])->latest('id')->limit(25)->get();
        $stats = Message::whereIn('campaign_id', $campaigns->pluck('id'))
            ->selectRaw('campaign_id, status, count(*) as c')->groupBy('campaign_id', 'status')->get()->groupBy('campaign_id');
        $replies = Message::where('direction', 'in')->whereIn('contact_id', fn ($q) => $q->select('contact_id')->from('messages')->whereIn('campaign_id', $campaigns->pluck('id')))
            ->where('created_at', '>=', $from)->distinct()->count('contact_id');

        $rows = $campaigns->map(function (Campaign $c) use ($stats) {
            $s = ($stats[$c->id] ?? collect())->pluck('c', 'status');
            $read = (int) ($s['read'] ?? 0);
            $delivered = (int) ($s['delivered'] ?? 0) + $read;
            $sent = (int) ($s['sent'] ?? 0) + $delivered;
            $failed = (int) ($s['failed'] ?? 0);
            $total = $s->sum();

            return [
                'campaign' => $c, 'total' => $total, 'sent' => $sent, 'delivered' => $delivered, 'read' => $read, 'failed' => $failed,
                'readPct' => $delivered ? round($read * 100 / $delivered) : 0,
                'deliveredPct' => ($sent + $failed) ? round($delivered * 100 / ($sent + $failed)) : 0,
                'cost' => round($delivered * Campaign::rate($c->template?->category), 2),
            ];
        });

        return ['rows' => $rows, 'replies' => $replies];
    }

    /** Per agent: replies sent, chats assigned now, average time to answer a customer message. */
    private function agents(int $ws, Carbon $from, Carbon $to)
    {
        $members = $this->current->get()->users()->get();
        $replies = Message::where('direction', 'out')->whereNotNull('user_id')->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, count(*) as c, count(distinct contact_id) as chats')->groupBy('user_id')->get()->keyBy('user_id');
        $assigned = Contact::whereNotNull('assigned_user_id')->where('chat_status', '!=', 'closed')
            ->selectRaw('assigned_user_id, count(*) as c')->groupBy('assigned_user_id')->pluck('c', 'assigned_user_id');

        // Response time: for each agent reply, wait since the customer's first unanswered message.
        $waits = [];
        $lastIn = [];
        Message::whereBetween('created_at', [$from->copy()->subDay(), $to])
            ->orderBy('contact_id')->orderBy('created_at')->orderBy('id')
            ->limit(50000)->get(['contact_id', 'direction', 'user_id', 'created_at'])
            ->each(function ($m) use (&$waits, &$lastIn, $from) {
                if ($m->direction === 'in') {
                    $lastIn[$m->contact_id] ??= $m->created_at;

                    return;
                }
                if (! $m->user_id) {
                    return; // campaigns and bots do not count as a team answer
                }
                if (isset($lastIn[$m->contact_id]) && $m->created_at->gte($from)) {
                    $waits[$m->user_id][] = $lastIn[$m->contact_id]->diffInSeconds($m->created_at);
                }
                unset($lastIn[$m->contact_id]);
            });

        return $members->map(fn ($u) => [
            'user' => $u,
            'role' => $u->pivot->role,
            'replies' => (int) ($replies[$u->id]->c ?? 0),
            'chats' => (int) ($replies[$u->id]->chats ?? 0),
            'open' => (int) ($assigned[$u->id] ?? 0),
            'avgResponse' => empty($waits[$u->id]) ? null : (int) round(array_sum($waits[$u->id]) / count($waits[$u->id])),
        ])->sortByDesc('replies')->values();
    }

    /** Incoming messages by hour of day (India time): when customers are most active. */
    private function hours(\Closure $msgs): array
    {
        $hours = array_fill(0, 24, 0);
        $msgs()->where('direction', 'in')->select(['id', 'created_at'])->chunkById(5000, function ($rows) use (&$hours) {
            foreach ($rows as $m) {
                $hours[(int) $m->created_at->copy()->setTimezone(self::TIMEZONE)->format('G')]++;
            }
        });

        return $hours;
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = $this->daily($from, $to);
        $name = 'whatsapp-report-'.$from->copy()->setTimezone(self::TIMEZONE)->toDateString().'-to-'.$to->copy()->setTimezone(self::TIMEZONE)->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Sent', 'Delivered', 'Read', 'Failed', 'Received', 'New contacts'], escape: '\\');
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['out'], $r['delivered'], $r['read'], $r['failed'], $r['in'], $r['contacts']], escape: '\\');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
