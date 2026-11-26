<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappAccount;

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

        return view('dashboard', [
            'stats' => [
                'Connected numbers' => WhatsappAccount::where('status', 'connected')->count(),
                'Contacts' => Contact::count(),
                'Sent (30 days)' => $sent,
                'Delivered (30 days)' => $delivered,
                'Read (30 days)' => $read,
                'Replies (30 days)' => Message::where('direction', 'in')->where('created_at', '>=', $since)->count(),
            ],
            'campaigns' => Campaign::with('template')->latest()->limit(5)->get(),
            'hasNumber' => WhatsappAccount::exists(),
        ]);
    }
}
