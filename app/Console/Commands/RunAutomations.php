<?php

namespace App\Console\Commands;

use App\Jobs\SendAutomationTemplate;
use App\Models\Automation;
use App\Models\AutomationEnrollment;
use App\Models\Contact;
use App\Services\Campaigns\CampaignLauncher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RunAutomations extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Send due drip sequence steps and date reminders';

    public function handle(): void
    {
        Automation::withoutGlobalScopes()->where('active', true)->where('type', 'drip')
            ->each(function (Automation $drip) {
                $this->enroll($drip);
                $this->runDrip($drip);
            });

        Automation::withoutGlobalScopes()->where('active', true)->where('type', 'date')
            ->each(fn (Automation $a) => $this->runDate($a));
    }

    /** Contacts this drip should reach. */
    public static function audience(Automation $drip)
    {
        $audience = $drip->config['audience'] ?? [];

        return Contact::withoutGlobalScopes()->where('workspace_id', $drip->workspace_id)->subscribed()
            ->when(! empty($audience['groups']), fn ($q) => $q->whereHas('groups', fn ($g) => $g->withoutGlobalScopes()->whereIn('contact_groups.id', $audience['groups'])))
            ->when(! empty($audience['tags']), fn ($q) => CampaignLauncher::withAnyTag($q, $audience['tags']))
            ->when(empty($drip->config['enroll_new']), fn ($q) => $q->where('contacts.created_at', '<=', $drip->created_at));
    }

    private function enroll(Automation $drip): void
    {
        $first = $drip->config['steps'][0] ?? null;
        if (! $first) {
            return;
        }
        $runAt = now()->addSeconds(Automation::stepDelay($first));

        self::audience($drip)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('automation_enrollments')
                ->whereColumn('automation_enrollments.contact_id', 'contacts.id')->where('automation_id', $drip->id))
            ->select('contacts.id')
            ->chunkById(1000, function ($contacts) use ($drip, $runAt) {
                DB::table('automation_enrollments')->insertOrIgnore($contacts->map(fn ($c) => [
                    'workspace_id' => $drip->workspace_id, 'automation_id' => $drip->id, 'contact_id' => $c->id,
                    'step' => 0, 'next_run_at' => $runAt, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }, 'contacts.id', 'id');
    }

    private function runDrip(Automation $drip): void
    {
        $steps = array_values($drip->config['steps'] ?? []);

        AutomationEnrollment::withoutGlobalScopes()->with(['contact' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('automation_id', $drip->id)->where('status', 'active')->where('next_run_at', '<=', now())
            ->limit(1000)->get()
            ->each(function (AutomationEnrollment $e) use ($drip, $steps) {
                $step = $steps[$e->step] ?? null;
                if (! $step) {
                    $e->update(['status' => 'done', 'next_run_at' => null]);

                    return;
                }
                if (! $e->contact || $e->contact->opted_out_at || $e->contact->blocked_at) {
                    $e->update(['status' => 'stopped', 'next_run_at' => null]);

                    return;
                }
                if ($drip->claim($e->contact_id, 'drip:'.$e->step)) {
                    SendAutomationTemplate::dispatch($drip->id, $e->contact_id, $step);
                }
                $next = $steps[$e->step + 1] ?? null;
                $e->update([
                    'step' => $e->step + 1,
                    'status' => $next ? 'active' : 'done',
                    'next_run_at' => $next ? now()->addSeconds(Automation::stepDelay($next)) : null,
                ]);
            });
    }

    private function runDate(Automation $reminder): void
    {
        $config = $reminder->config;
        $local = now()->setTimezone($reminder->timezone());
        $doneKey = "automation:{$reminder->id}:date:".$local->toDateString();
        if ($local->format('H:i') < ($config['time'] ?? '09:00') || Cache::has($doneKey) || empty($config['field'])) {
            return;
        }

        $target = $local->copy()->addDays(max(0, (int) ($config['days_before'] ?? 0)));
        $yearly = $config['yearly'] ?? true;

        Contact::withoutGlobalScopes()->where('workspace_id', $reminder->workspace_id)->subscribed()->whereNotNull('fields')
            ->chunkById(1000, function ($contacts) use ($reminder, $config, $target, $yearly) {
                foreach ($contacts as $contact) {
                    $date = Automation::parseDate($contact->variable($config['field']));
                    if (! $date || $date[1] !== $target->month || $date[2] !== $target->day || (! $yearly && $date[0] !== $target->year)) {
                        continue;
                    }
                    if ($reminder->claim($contact->id, 'date:'.$target->toDateString())) {
                        SendAutomationTemplate::dispatch($reminder->id, $contact->id, $config);
                    }
                }
            });

        Cache::put($doneKey, true, now()->addDays(2));
    }
}
