<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Models\FlowSession;
use App\Models\WhatsappAccount;
use App\Services\Flows\FlowDefinition;
use App\Services\Flows\FlowStarters;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FlowController extends Controller
{
    public function index()
    {
        $flows = Flow::with('whatsappAccount')->latest()->get();

        return view('flows.index', [
            'flows' => $flows,
            'stats' => [
                'total' => $flows->count(),
                'active' => $flows->where('status', 'active')->count(),
                'triggered' => $flows->sum('triggered_count'),
                'completed' => $flows->sum('completed_count'),
                'live' => FlowSession::where('status', 'active')->where('last_interaction_at', '>=', now()->subDay())->count(),
            ],
            'starters' => FlowStarters::all(),
        ]);
    }

    public function create(Request $request)
    {
        $starter = array_key_exists($request->query('starter'), FlowStarters::all()) ? $request->query('starter') : 'blank';

        return $this->builder(new Flow([
            'name' => FlowStarters::all()[$starter][0],
            'triggers' => FlowStarters::all()[$starter][2],
            'status' => 'draft',
            'definition' => FlowStarters::definition($starter),
        ]));
    }

    public function edit(Flow $flow)
    {
        return $this->builder($flow);
    }

    public function store(Request $request)
    {
        $flow = Flow::create($this->validated($request));

        return redirect()->route('flows.edit', $flow)->with('status', 'Flow saved.'.($flow->status === 'active' ? ' It is live now.' : ''));
    }

    public function update(Request $request, Flow $flow)
    {
        $flow->update($this->validated($request, $flow));

        return redirect()->route('flows.edit', $flow)->with('status', 'Flow saved.'.($flow->status === 'active' ? ' It is live now.' : ''));
    }

    public function toggle(Flow $flow)
    {
        if ($flow->status !== 'active' && ($errors = FlowDefinition::errors($flow->definition))) {
            return back()->withErrors($errors);
        }
        $flow->update(['status' => $flow->status === 'active' ? 'draft' : 'active']);

        return back()->with('status', $flow->status === 'active' ? "\"{$flow->name}\" is live." : "\"{$flow->name}\" is paused.");
    }

    public function duplicate(Flow $flow)
    {
        $copy = $flow->replicate(['triggered_count', 'completed_count']);
        $copy->name = $flow->name.' (copy)';
        $copy->status = 'draft';
        $copy->save();

        return redirect()->route('flows.edit', $copy)->with('status', 'Flow duplicated as a draft.');
    }

    public function destroy(Flow $flow)
    {
        $flow->delete();

        return redirect()->route('flows.index')->with('status', 'Flow deleted.');
    }

    private function builder(Flow $flow)
    {
        return view('flows.builder', ['flow' => $flow, 'accounts' => WhatsappAccount::all()]);
    }

    private function validated(Request $request, ?Flow $flow = null): array
    {
        $ws = app(CurrentWorkspace::class)->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'triggers' => ['required', 'string', 'max:500'],
            'whatsapp_account_id' => ['nullable', Rule::exists('whatsapp_accounts', 'id')->where('workspace_id', $ws)],
            'status' => ['required', 'in:draft,active'],
            'definition' => ['required', 'json'],
        ]);

        $triggers = collect(preg_split('/[,\n]+/', $data['triggers']))->map(fn ($t) => trim($t))->filter()->unique(fn ($t) => mb_strtolower($t))->values();
        if ($triggers->isEmpty()) {
            throw ValidationException::withMessages(['triggers' => 'Add at least one trigger keyword.']);
        }

        $definition = json_decode($data['definition'], true);
        if ($errors = FlowDefinition::errors($definition)) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        if ($data['status'] === 'active') {
            $clash = Flow::where('status', 'active')->when($flow, fn ($q) => $q->whereKeyNot($flow->id))->get()
                ->first(fn (Flow $f) => $triggers->contains(fn ($t) => $f->matches($t)));
            if ($clash) {
                throw ValidationException::withMessages(['triggers' => "The live flow \"{$clash->name}\" already uses one of these keywords."]);
            }
        }

        return [
            'name' => $data['name'],
            'triggers' => $triggers->all(),
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'status' => $data['status'],
            'definition' => $definition,
        ];
    }
}
