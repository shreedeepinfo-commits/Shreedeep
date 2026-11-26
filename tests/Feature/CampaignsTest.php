<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappAccount $account;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.graph_version' => 'v24.0', 'whatsapp.messages_per_second' => 1000]);
        $this->user = User::factory()->create();
        $ws = $this->user->createWorkspace('Shop')->id;
        $this->account = WhatsappAccount::create(['workspace_id' => $ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok']);
        $this->template = MessageTemplate::create([
            'workspace_id' => $ws, 'whatsapp_account_id' => $this->account->id, 'name' => 'offer', 'language' => 'hi',
            'category' => 'MARKETING', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}} off in {{3}}']],
        ]);
        $group = ContactGroup::create(['workspace_id' => $ws, 'name' => 'VIP']);
        $a = Contact::create(['workspace_id' => $ws, 'phone' => '919000000001', 'name' => 'Ravi', 'fields' => ['city' => 'Pune']]);
        $b = Contact::create(['workspace_id' => $ws, 'phone' => '919000000002', 'name' => null]);
        $c = Contact::create(['workspace_id' => $ws, 'phone' => '919000000003', 'name' => 'Out', 'opted_out_at' => now()]);
        Contact::create(['workspace_id' => $ws, 'phone' => '919000000004', 'name' => 'Not in group']);
        $group->contacts()->attach([$a->id, $b->id, $c->id]);
    }

    private function fakeSends(): void
    {
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
    }

    private function launch(array $overrides = [])
    {
        return $this->actingAs($this->user)->post('/campaigns', array_merge([
            'name' => 'Diwali',
            'message_template_id' => $this->template->id,
            'audience' => 'groups',
            'groups' => [ContactGroup::first()->id],
            'variables' => ['body' => [
                ['type' => 'name', 'fallback' => 'friend'],
                ['type' => 'text', 'value' => '20%'],
                ['type' => 'field', 'value' => 'city', 'fallback' => 'your city'],
            ]],
            'send' => 'now',
        ], $overrides));
    }

    public function test_campaign_sends_personalised_templates_to_subscribed_group_members(): void
    {
        $n = 0;
        Http::fake(['*/555/messages' => function () use (&$n) {
            $n++;

            return Http::response(['messages' => [['id' => "wamid.{$n}"]]]);
        }]);

        $this->launch()->assertRedirect();

        $campaign = Campaign::first();
        $this->assertSame(2, $campaign->total); // opted-out and non-member skipped
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(2, Message::where('status', 'sent')->whereNotNull('wamid')->count());

        Http::assertSent(fn ($r) => $r['to'] === '919000000001'
            && $r['template']['name'] === 'offer' && $r['template']['language']['code'] === 'hi'
            && array_column($r['template']['components'][0]['parameters'], 'text') === ['Ravi', '20%', 'Pune']);
        Http::assertSent(fn ($r) => $r['to'] === '919000000002'
            && array_column($r['template']['components'][0]['parameters'], 'text') === ['friend', '20%', 'your city']);
        $this->assertSame('Hi Ravi, 20% off in Pune', Message::where('phone', '919000000001')->value('body'));

        $this->get("/campaigns/{$campaign->id}")->assertOk()->assertSee('Sent');
        $this->get('/campaigns')->assertOk()->assertSee('Diwali');
    }

    public function test_failed_sends_are_recorded_with_reason(): void
    {
        Http::fake(['*/555/messages' => Http::response(['error' => ['message' => 'Recipient not on WhatsApp', 'code' => 131026]], 400)]);

        $this->launch();

        $this->assertSame(2, Message::where('status', 'failed')->count());
        $this->assertSame('131026', Message::first()->error_code);
        $this->get('/campaigns/'.Campaign::first()->id)->assertSee('Recipient not on WhatsApp');
    }

    public function test_scheduled_campaign_waits_for_scheduler(): void
    {
        $this->fakeSends();

        $this->launch(['send' => 'later', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')]);
        $campaign = Campaign::first();
        $this->assertSame('scheduled', $campaign->status);
        Http::assertNothingSent();

        $this->artisan('campaigns:dispatch-due');
        Http::assertNothingSent();

        $this->travel(2)->hours();
        $this->artisan('campaigns:dispatch-due');
        $this->assertSame('completed', $campaign->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_paused_campaign_does_not_send_until_resumed(): void
    {
        $this->fakeSends();
        $this->launch(['send' => 'later', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')]);
        $campaign = Campaign::first();

        $this->post("/campaigns/{$campaign->id}/pause");
        $this->travel(2)->hours();
        $this->artisan('campaigns:dispatch-due');
        Http::assertNothingSent();

        $this->post("/campaigns/{$campaign->id}/resume");
        Http::assertSentCount(2);
        $this->assertSame('completed', $campaign->fresh()->status);
    }

    public function test_export_report_csv(): void
    {
        $this->fakeSends();
        $this->launch();
        $csv = $this->get('/campaigns/'.Campaign::first()->id.'/export')->streamedContent();
        $this->assertStringContainsString('919000000001,Ravi,sent', $csv);
    }

    public function test_unapproved_template_is_rejected(): void
    {
        $this->template->update(['status' => 'PENDING']);
        $this->launch()->assertSessionHasErrors('message_template_id');
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->user)->get('/campaigns/create')->assertOk()->assertSee('Launch campaign');
    }
}
