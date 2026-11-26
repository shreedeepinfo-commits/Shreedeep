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
            ->when(empty($audience['all']) && empty($audience['tags']), fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereIn('contact_groups.id', $audience['groups'] ?? [])))
            ->when(empty($audience['all']) && ! empty($audience['tags']), fn ($q) => self::withAnyTag($q, $audience['tags']))
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

    /** Contacts having at least one of the tags (tags are stored as a JSON array). */
    public static function withAnyTag($query, array $tags)
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhere('tags', 'like', '%'.json_encode((string) $tag).'%');
            }
        });
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
