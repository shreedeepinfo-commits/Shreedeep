<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['name' => 'Asha Owner']);
        $this->ws = $this->user->createWorkspace('Homes');
        app(CurrentWorkspace::class)->set($this->ws, 'owner');

        $account = WhatsappAccount::create(['waba_id' => '9', 'phone_number_id' => '555', 'access_token' => 't']);
        $template = MessageTemplate::create(['whatsapp_account_id' => $account->id, 'name' => 'offer', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => []]);
        $campaign = Campaign::create(['whatsapp_account_id' => $account->id, 'message_template_id' => $template->id, 'name' => 'Diwali Sale', 'status' => 'completed', 'total' => 4, 'audience' => ['all' => true]]);
        $contact = Contact::create(['phone' => '919800000001', 'tags' => ['vip']]);

        $msg = fn (array $a) => Message::create($a + ['whatsapp_account_id' => $account->id, 'contact_id' => $contact->id, 'phone' => $contact->phone, 'direction' => 'out', 'type' => 'template']);
        foreach (['read', 'read', 'delivered', 'failed'] as $st) {
            $msg(['campaign_id' => $campaign->id, 'status' => $st, 'error_code' => $st === 'failed' ? '131026' : null, 'error_message' => $st === 'failed' ? 'Not on WhatsApp' : null]);
        }
        $in = $msg(['direction' => 'in', 'type' => 'text', 'status' => 'received', 'body' => 'Interested']);
        $in->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $msg(['type' => 'text', 'status' => 'sent', 'user_id' => $this->user->id, 'body' => 'Sure!']);
        $msg(['type' => 'text', 'status' => 'sent', 'payload' => ['source' => 'api']]);

        // Another workspace's data must never show up.
        $other = User::factory()->create()->createWorkspace('Other');
        Message::withoutGlobalScopes()->create(['workspace_id' => $other->id, 'whatsapp_account_id' => $account->id, 'phone' => '1', 'direction' => 'out', 'status' => 'failed', 'error_message' => 'SECRET-OTHER']);
        app(CurrentWorkspace::class)->set(null);
    }

    public function test_report_shows_funnel_sources_campaigns_and_agents(): void
    {
        $res = $this->actingAs($this->user)->get('/analytics')->assertOk();
        $kpi = $res->viewData('kpi');
        $this->assertSame(6, $kpi['attempted']);   // 4 campaign + 1 agent + 1 API (other workspace excluded)
        $this->assertSame(3, $kpi['delivered']);   // 2 read + 1 delivered
        $this->assertSame(1, $kpi['failed']);
        $this->assertSame(100.0, $kpi['replyRate']); // the one messaged customer replied
        $res->assertSee('Diwali Sale')->assertSee('Not on WhatsApp')->assertDontSee('SECRET-OTHER')->assertSee('Asha Owner');

        $sources = $res->viewData('sources');
        $this->assertSame(4, $sources['campaign']['count']);
        $this->assertSame(1, $sources['agent']['count']);
        $this->assertSame(1, $sources['api']['count']);

        $agent = $res->viewData('agents')->firstWhere('user.id', $this->user->id);
        $this->assertSame(1, $agent['replies']);
        $this->assertEqualsWithDelta(600, $agent['avgResponse'], 5);
    }

    public function test_ranges_and_csv_export(): void
    {
        $this->actingAs($this->user)->get('/analytics?days=7')->assertOk();
        $this->get('/analytics?from=2026-01-01&to=2026-01-31')->assertOk()->assertSee('No campaigns in this period');
        $this->get('/analytics?from=2026-02-01&to=2026-01-01')->assertSessionHasErrors('to');

        $csv = $this->get('/analytics/export?days=7')->assertOk()->streamedContent();
        $this->assertStringContainsString('Date,Sent,Delivered,Read,Failed,Received', $csv);
        $this->assertSame(8, substr_count(trim($csv), "\n") + 1); // header + 7 days
    }

    public function test_agents_cannot_open_analytics(): void
    {
        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/analytics')->assertForbidden();
    }
}
