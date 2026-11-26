<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $since = now()->subDays(30);
        $out = Message::where('direction', 'out')->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $read = (int) ($out['read'] ?? 0);
        $delivered = (int) ($out['delivered'] ?? 0) + $read;
        $sent = (int) ($out['sent'] ?? 0) + $delivered;
        $failed = (int) ($out['failed'] ?? 0);
        $attempted = $sent + $failed;
        $pct = fn (int $n) => $attempted ? round($n * 100 / $attempted, 1) : 0;

        // Last 7 days, oldest first.
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());
        $first = $days->first();
        $outByDay = Message::where('direction', 'out')->where('created_at', '>=', $first)->get(['created_at'])
            ->countBy(fn ($m) => $m->created_at->toDateString());
        $inByDay = Message::where('direction', 'in')->where('created_at', '>=', $first)->get(['created_at'])
            ->countBy(fn ($m) => $m->created_at->toDateString());
        $contactsByDay = Contact::where('created_at', '>=', $first)->get(['created_at'])
            ->countBy(fn ($c) => $c->created_at->toDateString());

        $account = WhatsappAccount::where('status', 'connected')->first() ?? WhatsappAccount::first();
        $contacts = Contact::count();
        $checklist = [
            ['Connect your WhatsApp number', 'Link your number with Meta in 2 minutes', (bool) $account, route('whatsapp.index')],
            ['Get a template approved', 'Create or sync a message template', MessageTemplate::approved()->exists(), route('templates.index')],
            ['Upload your contacts', 'Import your customer list from CSV', $contacts > 0, route('contacts.index')],
            ['Send your first campaign', 'Reach your customers in bulk', Campaign::exists(), route('campaigns.create')],
            ['Set up your first chatbot', 'Answer "hi" with buttons, 24/7', Flow::exists(), route('flows.index')],
        ];

        if (! app(\App\Support\CurrentWorkspace::class)->isManager()) {
            // Agents cannot open setup pages; send them to their chats instead.
            $checklist = array_map(fn ($row) => [$row[0], $row[1], $row[2], $row[3] === route('contacts.index') ? $row[3] : route('inbox.index')], $checklist);
        }

        return view('dashboard', [
            'account' => $account,
            'contacts' => $contacts,
            'campaignsThisMonth' => Campaign::where('created_at', '>=', now()->startOfMonth())->count(),
            'funnel' => [
                'sent' => $sent, 'delivered' => $delivered, 'read' => $read, 'failed' => $failed,
                'deliveredPct' => $pct($delivered), 'readPct' => $pct($read), 'failedPct' => $pct($failed),
            ],
            'overview' => [
                'totalMessages' => Message::count(),
                'activeChatbots' => Flow::where('status', 'active')->count(),
            ],
            'replies' => Message::where('direction', 'in')->where('created_at', '>=', $since)->count(),
            'chart' => [
                'labels' => $days->map(fn (Carbon $d) => $d->format('d M'))->all(),
                'outbound' => $days->map(fn ($d) => $outByDay[$d->toDateString()] ?? 0)->all(),
                'inbound' => $days->map(fn ($d) => $inByDay[$d->toDateString()] ?? 0)->all(),
                'contacts' => $days->map(fn ($d) => $contactsByDay[$d->toDateString()] ?? 0)->all(),
            ],
            'checklist' => $checklist,
            'campaigns' => Campaign::with('template')->latest()->limit(5)->get(),
        ]);
    }
}
