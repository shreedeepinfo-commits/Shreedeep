<?php

namespace App\Services\Campaigns;

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class CampaignLauncher
{
    /** Create one queued message per subscribed contact in the audience. */
    public function prepare(Campaign $campaign): int
    {
        $audience = $campaign->audience ?? [];
        $query = Contact::withoutGlobalScopes()
            ->where('workspace_id', $campaign->workspace_id)
            ->subscribed()
            ->when(empty($audience['all']), fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereIn('contact_groups.id', $audience['groups'] ?? [])))
            ->select(['id', 'phone']);

        $now = now();
        $total = 0;
        $query->chunkById(1000, function ($contacts) use ($campaign, $now, &$total) {
            $rows = $contacts->map(fn ($c) => [
                'workspace_id' => $campaign->workspace_id,
                'whatsapp_account_id' => $campaign->whatsapp_account_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $c->id,
                'direction' => 'out',
                'phone' => $c->phone,
                'type' => 'template',
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('messages')->insert($rows);
            $total += count($rows);
        });

        $campaign->update(['total' => $total]);

        return $total;
    }

    /** Queue a send job for every message still queued. Safe to call again on resume. */
    public function start(Campaign $campaign): void
    {
        $campaign->update(['status' => 'running', 'started_at' => $campaign->started_at ?? now()]);

        $campaign->messages()->withoutGlobalScopes()->where('status', 'queued')->select('id')
            ->chunkById(1000, function ($messages) {
                foreach ($messages as $message) {
                    SendCampaignMessage::dispatch($message->id);
                }
            });

        $this->completeIfDone($campaign);
    }

    public function completeIfDone(Campaign $campaign): void
    {
        $pending = $campaign->messages()->withoutGlobalScopes()->where('status', 'queued')->exists();
        if (! $pending) {
            Campaign::withoutGlobalScopes()->whereKey($campaign->id)->where('status', 'running')
                ->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
