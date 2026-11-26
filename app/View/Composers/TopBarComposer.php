<?php

namespace App\View\Composers;

use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Numbers shown in the panel's top bar: this month's messages by Meta category and the active WhatsApp number. */
class TopBarComposer
{
    private static ?array $cache = null;

    public function compose(View $view): void
    {
        if (! auth()->check()) {
            return;
        }

        self::$cache ??= $this->data();
        $view->with('topBar', self::$cache);
    }

    private function data(): array
    {
        $monthStart = now()->startOfMonth();
        $byCategory = Message::query()
            ->where('messages.direction', 'out')
            ->where('messages.created_at', '>=', $monthStart)
            ->whereNotIn('messages.status', ['queued', 'failed'])
            ->leftJoin('campaigns', 'campaigns.id', '=', 'messages.campaign_id')
            ->leftJoin('message_templates', 'message_templates.id', '=', 'campaigns.message_template_id')
            ->select(DB::raw("coalesce(message_templates.category, 'SERVICE') as category"), DB::raw('count(*) as c'))
            ->groupBy('category')
            ->pluck('c', 'category');

        return [
            'marketing' => (int) ($byCategory['MARKETING'] ?? 0),
            'utility' => (int) ($byCategory['UTILITY'] ?? 0),
            'service' => (int) ($byCategory['SERVICE'] ?? 0),
            'account' => WhatsappAccount::where('status', 'connected')->first(),
            'unread' => (int) Contact::query()
                ->when(! app(\App\Support\CurrentWorkspace::class)->isManager(), fn ($q) => $q->where(fn ($w) => $w->whereNull('assigned_user_id')->orWhere('assigned_user_id', auth()->id())))
                ->sum('unread_count'),
            'workspaces' => auth()->user()->workspaces()->orderBy('name')->get(),
        ];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
