<?php

namespace App\Http\Controllers;

use App\Console\Commands\RunAutomations;
use App\Models\Automation;
use App\Models\AutomationEnrollment;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\MessageTemplate;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    /** Only one of these per workspace. */
    private const SINGLE = ['welcome', 'away'];

    public function index()
    {
        $automations = Automation::latest('id')->get();
        $enrollments = AutomationEnrollment::selectRaw('automation_id, status, count(*) as n')->groupBy('automation_id', 'status')->get()
            ->groupBy('automation_id')->map(fn ($rows) => $rows->pluck('n', 'status'));

        return view('automations.index', [
            'automations' => $automations->groupBy('type'),
            'enrollments' => $enrollments,
            'templates' => MessageTemplate::pluck('name', 'id'),
            'stats' => [
                'total' => $automations->count(),
                'active' => $automations->where('active', true)->count(),
                'sent' => $automations->sum('sent_count'),
                'in_drip' => $enrollments->sum(fn ($s) => $s['active'] ?? 0),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $type = array_key_exists($request->query('type'), Automation::TYPES) ? $request->query('type') : 'keyword';
        if (in_array($type, self::SINGLE, true) && ($existing = Automation::where('type', $type)->first())) {
            return redirect()->route('automations.edit', $existing);
        }

        return $this->form(new Automation(['type' => $type, 'active' => true, 'name' => Automation::TYPES[$type][0], 'config' => $this->defaults($type)]));
    }

    public function edit(Automation $automation)
    {
        return $this->form($automation);
    }

    public function store(Request $request)
    {
        $type = $request->validate(['type' => ['required', Rule::in(array_keys(Automation::TYPES))]])['type'];
        if (in_array($type, self::SINGLE, true) && Automation::where('type', $type)->exists()) {
            return redirect()->route('automations.edit', Automation::where('type', $type)->first());
        }
        $automation = Automation::create(['type' => $type] + $this->validated($request, $type));

        return redirect()->route('automations.index')->with('status', "\"{$automation->name}\" saved.".($automation->active ? ' It is on now.' : ''));
    }

    public function update(Request $request, Automation $automation)
    {
        $automation->update($this->validated($request, $automation->type));

        return redirect()->route('automations.index')->with('status', "\"{$automation->name}\" saved.");
    }

    public function toggle(Automation $automation)
    {
        $automation->update(['active' => ! $automation->active]);

        return back()->with('status', "\"{$automation->name}\" is ".($automation->active ? 'on' : 'off').'.');
    }

    public function destroy(Automation $automation)
    {
        $automation->delete();

        return redirect()->route('automations.index')->with('status', 'Automation deleted.');
    }

    private function form(Automation $automation)
    {
        $templates = MessageTemplate::approved()->orderBy('name')->get();

        return view('automations.form', [
            'automation' => $automation,
            'templates' => $templates,
            'templateMeta' => $templates->mapWithKeys(fn ($t) => [$t->id => [
                'name' => $t->name,
                'body' => $t->bodyText(),
                'vars' => $t->bodyVariableCount(),
                'header' => $t->headerFormat(),
                'headerVars' => $t->headerVariableCount(),
            ]]),
            'groups' => ContactGroup::withCount('contacts')->orderBy('name')->get(),
            'fields' => Contact::whereNotNull('fields')->limit(500)->pluck('fields')->flatMap(fn ($f) => array_keys((array) $f))->unique()->sort()->values(),
            'audienceCount' => $automation->type === 'drip' && $automation->exists ? RunAutomations::audience($automation)->count() : null,
        ]);
    }

    private function defaults(string $type): array
    {
        return match ($type) {
            'welcome' => ['message' => "Hi {{name}}! 👋 Thanks for messaging us. How can we help you today?"],
            'away' => ['message' => "Thanks for your message! We are closed right now and will reply as soon as we open.", 'timezone' => Automation::DEFAULT_TIMEZONE, 'hours' => Automation::defaultHours()],
            'keyword' => ['keywords' => [], 'match' => 'contains', 'reply' => ''],
            'drip' => ['audience' => ['groups' => [], 'tags' => []], 'enroll_new' => true, 'steps' => [['template_id' => null, 'delay_days' => 0, 'delay_hours' => 0]]],
            'date' => ['field' => 'birthday', 'days_before' => 0, 'time' => '09:00', 'yearly' => true, 'timezone' => Automation::DEFAULT_TIMEZONE, 'template_id' => null],
        };
    }

    private function validated(Request $request, string $type): array
    {
        $ws = app(CurrentWorkspace::class)->id();
        $template = ['required', Rule::exists('message_templates', 'id')->where('workspace_id', $ws)->where('status', 'APPROVED')];
        $vars = [
            'variables' => ['nullable', 'array'],
            'variables.header.type' => ['nullable', 'in:name,phone,email,field,text'],
            'variables.header.value' => ['nullable', 'string', 'max:200'],
            'variables.body' => ['nullable', 'array', 'max:20'],
            'variables.body.*.type' => ['required', 'in:name,phone,email,field,text'],
            'variables.body.*.value' => ['nullable', 'string', 'max:200'],
            'variables.body.*.fallback' => ['nullable', 'string', 'max:200'],
            'header_media_url' => ['nullable', 'url', 'max:2000'],
        ];
        $prefixed = fn (string $p) => collect($vars)->mapWithKeys(fn ($r, $k) => [$p.$k => $r])->all();

        $rules = ['name' => ['required', 'string', 'max:80'], 'active' => ['nullable', 'boolean']] + match ($type) {
            'welcome' => ['message' => ['required', 'string', 'max:4096']],
            'away' => [
                'message' => ['required', 'string', 'max:4096'],
                'timezone' => ['required', 'timezone'],
                'hours' => ['required', 'array'],
                'hours.*.open' => ['nullable', 'boolean'],
                'hours.*.from' => ['required', 'date_format:H:i'],
                'hours.*.to' => ['required', 'date_format:H:i'],
            ],
            'keyword' => [
                'keywords' => ['required', 'string', 'max:500'],
                'match' => ['required', 'in:exact,contains'],
                'reply' => ['required', 'string', 'max:4096'],
            ],
            'drip' => [
                'groups' => ['nullable', 'array'],
                'groups.*' => [Rule::exists('contact_groups', 'id')->where('workspace_id', $ws)],
                'tags' => ['nullable', 'string', 'max:300'],
                'enroll_new' => ['nullable', 'boolean'],
                'steps' => ['required', 'array', 'min:1', 'max:10'],
                'steps.*.template_id' => $template,
                'steps.*.delay_days' => ['required', 'integer', 'min:0', 'max:365'],
                'steps.*.delay_hours' => ['required', 'integer', 'min:0', 'max:23'],
            ] + $prefixed('steps.*.'),
            'date' => [
                'field' => ['required', 'string', 'max:40', 'regex:/^[\w\- ]+$/u'],
                'days_before' => ['required', 'integer', 'min:0', 'max:30'],
                'time' => ['required', 'date_format:H:i'],
                'yearly' => ['nullable', 'boolean'],
                'timezone' => ['required', 'timezone'],
                'template_id' => $template,
            ] + $vars,
        };
        $data = $request->validate($rules, [
            'steps.*.template_id.required' => 'Choose an approved template for every step.',
            'template_id.required' => 'Choose an approved template.',
        ]);

        $list = fn (?string $s) => collect(preg_split('/[,\n]+/', (string) $s))->map(fn ($w) => trim($w))->filter()->unique()->values()->all();
        $step = fn (array $s) => [
            'template_id' => (int) $s['template_id'],
            'variables' => Arr::only($s['variables'] ?? [], ['header', 'body']),
            'header_media_url' => $s['header_media_url'] ?? null,
        ];

        $config = match ($type) {
            'welcome' => ['message' => $data['message']],
            'away' => [
                'message' => $data['message'],
                'timezone' => $data['timezone'],
                'hours' => collect(Automation::DAYS)->mapWithKeys(fn ($l, $d) => [$d => [
                    'open' => (bool) ($data['hours'][$d]['open'] ?? false),
                    'from' => $data['hours'][$d]['from'] ?? '10:00',
                    'to' => $data['hours'][$d]['to'] ?? '19:00',
                ]])->all(),
            ],
            'keyword' => ['keywords' => $list($data['keywords']), 'match' => $data['match'], 'reply' => $data['reply']],
            'drip' => [
                'audience' => ['groups' => array_map('intval', $data['groups'] ?? []), 'tags' => $list($data['tags'] ?? '')],
                'enroll_new' => (bool) ($data['enroll_new'] ?? false),
                'steps' => collect($data['steps'])->values()->map(fn ($s) => $step($s) + [
                    'delay_days' => (int) $s['delay_days'], 'delay_hours' => (int) $s['delay_hours'],
                ])->all(),
            ],
            'date' => $step($data) + [
                'field' => trim($data['field']),
                'days_before' => (int) $data['days_before'],
                'time' => $data['time'],
                'yearly' => (bool) ($data['yearly'] ?? false),
                'timezone' => $data['timezone'],
            ],
        };

        return ['name' => $data['name'], 'active' => (bool) ($data['active'] ?? false), 'config' => $config];
    }
}
