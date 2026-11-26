<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactNote;
use Illuminate\Http\Request;

/** Sales pipeline board and follow-up reminders. */
class CrmController extends Controller
{
    private const PER_STAGE = 100;

    public function pipeline(Request $request)
    {
        $mine = $request->boolean('mine');
        $base = Contact::query()->when($mine, fn ($q) => $q->where('assigned_user_id', $request->user()->id));

        $stages = collect(Contact::LEAD_STATUSES)->map(function ($meta, $key) use ($base) {
            $query = (clone $base)->where('lead_status', $key);

            return [
                'key' => $key,
                'label' => $meta[0],
                'color' => $meta[1],
                'count' => (clone $query)->count(),
                'value' => (float) (clone $query)->sum('deal_value'),
                'contacts' => $query->with('assignedUser')->orderByDesc('updated_at')->limit(self::PER_STAGE)->get(),
            ];
        });

        return view('crm.pipeline', ['stages' => $stages, 'mine' => $mine, 'perStage' => self::PER_STAGE]);
    }

    public function followUps(Request $request)
    {
        $mine = $request->boolean('mine');
        $all = Contact::with('assignedUser')->whereNotNull('follow_up_at')
            ->when($mine, fn ($q) => $q->where('assigned_user_id', $request->user()->id))
            ->orderBy('follow_up_at')->limit(500)->get();

        return view('crm.follow-ups', [
            'mine' => $mine,
            'groups' => [
                'Overdue' => $all->filter(fn ($c) => $c->follow_up_at->lt(now()->startOfDay())),
                'Today' => $all->filter(fn ($c) => $c->follow_up_at->isToday()),
                'Upcoming' => $all->filter(fn ($c) => $c->follow_up_at->gt(now()->endOfDay())),
            ],
        ]);
    }

    public function done(Request $request, Contact $contact)
    {
        ContactNote::create(['contact_id' => $contact->id, 'user_id' => $request->user()->id, 'system' => true,
            'body' => 'Follow-up done'.($contact->follow_up_note ? ': '.$contact->follow_up_note : '')]);
        $contact->update(['follow_up_at' => null, 'follow_up_note' => null]);

        return back()->with('status', 'Follow-up marked as done.');
    }
}
