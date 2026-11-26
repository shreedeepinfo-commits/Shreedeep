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

class CampaignsProTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $ws;

    private WhatsappAccount $account;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.messages_per_second' => 1000]);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
        $this->user = User::factory()->create();
        $this->ws = $this->user->createWorkspace('Shop')->id;
        $this->account = WhatsappAccount::create(['workspace_id' => $this->ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'status' => 'connected']);
        $this->template = MessageTemplate::create([
            'workspace_id' => $this->ws, 'whatsapp_account_id' => $this->account->id, 'name' => 'offer', 'language' => 'en',
            'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello there']],
        ]);
        foreach ([['919000000001', ['vip', 'pune']], ['919000000002', ['pune']], ['919000000003', ['mumbai']], ['919000000004', null]] as [$phone, $tags]) {
            Contact::create(['workspace_id' => $this->ws, 'phone' => $phone, 'name' => 'C'.$phone, 'tags' => $tags]);
        }
        $this->actingAs($this->user);
    }

    private function launch(array $overrides = []): Campaign
    {
        $this->post('/campaigns', array_merge([
            'name' => 'Promo', 'message_template_id' => $this->template->id, 'audience' => 'tags', 'tags' => ['pune'], 'send' => 'now',
        ], $overrides))->assertRedirect();

        return Campaign::latest('id')->firstOrFail();
    }

    public function test_tag_audience_and_cost_estimate(): void
    {
        $this->get('/campaigns/create')->assertOk()->assertSee('By tags')->assertSee('pune')->assertSee('approx. Meta charge');

        $campaign = $this->launch();
        $this->assertSame(2, $campaign->total);
        $this->assertEqualsCanonicalizing(['919000000001', '919000000002'], Message::where('campaign_id', $campaign->id)->pluck('phone')->all());
        $this->assertSame(round(2 * 0.8631, 2), $campaign->estimatedCost());
        $this->get("/campaigns/{$campaign->id}")->assertOk()->assertSee('Meta charge')->assertSee('Follow-up campaign');
    }

    public function test_duplicate_prefills_the_create_form(): void
    {
        $campaign = $this->launch(['tags' => ['mumbai']]);
        $this->post("/campaigns/{$campaign->id}/duplicate")->assertRedirect("/campaigns/create?from={$campaign->id}");
        $this->get("/campaigns/create?from={$campaign->id}")->assertOk()->assertSee('Promo (copy)')
            ->assertSee('value="mumbai" data-count="1" class="accent-brand-600 aud-count" checked', false);
    }

    public function test_retry_only_temporary_failures_once(): void
    {
        $campaign = $this->launch();
        [$a, $b] = Message::where('campaign_id', $campaign->id)->orderBy('id')->get()->all();
        $a->update(['status' => 'failed', 'error_code' => '131049', 'error_message' => 'Meta chose not to deliver']);
        $b->update(['status' => 'failed', 'error_code' => '131026', 'error_message' => 'Not on WhatsApp']);
        $campaign->update(['status' => 'completed']);

        $this->get("/campaigns/{$campaign->id}")->assertSee('Retry 1 failed');
        Http::fake(['*/555/messages' => Http::response(['messages' => [['id' => 'wamid.retry1']]])]);
        $this->post("/campaigns/{$campaign->id}/retry")->assertRedirect();

        $a->refresh();
        $this->assertSame('sent', $a->status);
        $this->assertSame(1, $a->retry_count);
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertSame('completed', $campaign->fresh()->status);

        // Fails again: no second retry.
        $a->update(['status' => 'failed', 'error_code' => '131049']);
        $this->post("/campaigns/{$campaign->id}/retry")->assertSessionHasErrors('retry');
    }

    public function test_retarget_readers_and_repliers_into_a_list(): void
    {
        $campaign = $this->launch();
        $msgs = Message::where('campaign_id', $campaign->id)->orderBy('id')->get();
        $msgs[0]->update(['status' => 'read']);
        $msgs[1]->update(['status' => 'delivered']);
        Message::create(['whatsapp_account_id' => $this->account->id, 'contact_id' => $msgs[1]->contact_id, 'direction' => 'in', 'phone' => $msgs[1]->phone, 'type' => 'text', 'body' => 'Interested', 'status' => 'received']);

        $this->post("/campaigns/{$campaign->id}/retarget", ['segment' => 'read'])->assertRedirect();
        $list = ContactGroup::where('name', 'Promo · Read')->firstOrFail();
        $this->assertSame([$msgs[0]->contact_id], $list->contacts()->pluck('contacts.id')->all());

        $this->post("/campaigns/{$campaign->id}/retarget", ['segment' => 'unread'])->assertRedirect();
        $this->assertSame([$msgs[1]->contact_id], ContactGroup::where('name', 'Promo · Not read')->first()->contacts()->pluck('contacts.id')->all());

        $this->post("/campaigns/{$campaign->id}/retarget", ['segment' => 'replied'])
            ->assertRedirect('/campaigns/create?group='.ContactGroup::where('name', 'Promo · Replied')->first()->id);

        $this->post("/campaigns/{$campaign->id}/retarget", ['segment' => 'failed'])->assertSessionHasErrors('segment');
        $this->get("/campaigns/{$campaign->id}")->assertSee('Replied');
    }

    public function test_tag_audience_is_required_when_chosen(): void
    {
        $this->post('/campaigns', ['name' => 'X', 'message_template_id' => $this->template->id, 'audience' => 'tags', 'send' => 'now'])
            ->assertSessionHasErrors('tags');
    }
}
