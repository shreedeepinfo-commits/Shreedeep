<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

/** Super admin: every customer's support tickets. */
class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), SupportTicket::STATUSES) ? $request->query('status') : null;

        return view('admin.tickets', [
            'tickets' => SupportTicket::withoutGlobalScopes()->with(['workspace', 'user'])
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'closed'))
                ->orderByRaw("case when priority = 'high' then 0 else 1 end")->orderBy('last_reply_at')->paginate(50)->withQueryString(),
            'status' => $status,
            'counts' => SupportTicket::withoutGlobalScopes()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        return view('admin.ticket', ['ticket' => $ticket->load('replies.user', 'user', 'workspace')]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'close' => ['nullable', 'boolean']]);
        $ticket->replies()->create(['user_id' => $request->user()->id, 'from_support' => true, 'body' => $data['body']]);
        $ticket->update(['status' => ! empty($data['close']) ? 'closed' : 'answered', 'last_reply_at' => now()]);

        return back()->with('status', 'Reply sent to '.$ticket->workspace?->name.'.');
    }

    public function status(Request $request, SupportTicket $ticket)
    {
        $ticket->update(['status' => $request->validate(['status' => ['required', 'in:open,answered,closed']])['status']]);

        return back()->with('status', 'Ticket updated.');
    }
}
