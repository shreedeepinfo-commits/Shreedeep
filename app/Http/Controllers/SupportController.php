<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Help & Support for customers: tickets, FAQ and setup guides. */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        return view('support.index', [
            'tab' => in_array($request->query('tab'), ['tickets', 'faq', 'guides'], true) ? $request->query('tab') : 'tickets',
            'tickets' => SupportTicket::with('user')->orderByRaw("case when status = 'closed' then 1 else 0 end")->latest('last_reply_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['nullable', 'in:normal,high'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        if (SupportTicket::where('status', 'open')->count() >= 10) {
            return back()->withErrors(['subject' => 'You already have 10 open tickets. Our team will reply soon.'])->withInput();
        }

        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id, 'subject' => $data['subject'], 'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal', 'status' => 'open', 'last_reply_at' => now(),
            ]);
            $ticket->replies()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

            return $ticket;
        });

        return redirect()->route('support.show', $ticket)->with('status', "Ticket {$ticket->number()} sent. Our team usually replies within a few hours.");
    }

    public function show(SupportTicket $ticket)
    {
        return view('support.show', ['ticket' => $ticket->load('replies.user', 'user')]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $ticket->replies()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $ticket->update(['status' => 'open', 'last_reply_at' => now()]); // a customer reply reopens it

        return back()->with('status', 'Reply sent.');
    }

    public function close(SupportTicket $ticket)
    {
        $ticket->update(['status' => 'closed']);

        return back()->with('status', 'Ticket closed. Reply any time to open it again.');
    }
}
