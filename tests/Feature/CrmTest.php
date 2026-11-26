<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Flow;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use App\Services\Campaigns\CampaignLauncher;
use App\Services\Flows\FlowStarters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Dilip Owner']);
        $this->ws = $this->owner->createWorkspace('Homes');
        $this->actingAs($this->owner);
        app(\App\Support\CurrentWorkspace::class)->set($this->ws, 'owner');
    }

    private function contact(array $attrs = []): Contact
    {
        return Contact::create(array_merge(['phone' => '91980000'.random_int(1000, 9999), 'name' => 'Lead '.random_int(1, 999)], $attrs));
    }

    public function test_contact_page_shows_lead_timeline_and_notes(): void
    {
        $c = $this->contact(['name' => 'Asha Patil', 'fields' => ['city' => 'Pune']]);
        $account = WhatsappAccount::create(['waba_id' => '9', 'phone_number_id' => '5', 'access_token' => 't']);
        Message::create(['whatsapp_account_id' => $account->id, 'contact_id' => $c->id, 'direction' => 'in', 'phone' => $c->phone, 'type' => 'text', 'body' => 'Need a 2BHK', 'status' => 'received']);

        $this->post("/contacts/{$c->id}/notes", ['body' => 'Budget 60 lakh'])->assertRedirect();
        $this->get("/contacts/{$c->id}")->assertOk()
            ->assertSee('Asha Patil')->assertSee('Need a 2BHK')->assertSee('Budget 60 lakh')->assertSee('Pune')->assertSee('Customer wrote');
    }

    public function test_lead_updates_and_stage_history(): void
    {
        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $c = $this->contact();
        $this->assertSame('new', $c->fresh()->lead_status);

        $this->post("/contacts/{$c->id}/lead", [
            'lead_status' => 'interested', 'deal_value' => '250000', 'assigned_user_id' => $agent->id,
            'follow_up_at' => now()->addDay()->format('Y-m-d\TH:i'), 'follow_up_note' => 'Site visit', 'opt_in_source' => 'Facebook ad',
        ])->assertRedirect();
        $c->refresh();
        $this->assertSame(['interested', '250000.00', $agent->id, 'Site visit', 'Facebook ad'], [$c->lead_status, $c->deal_value, $c->assigned_user_id, $c->follow_up_note, $c->opt_in_source]);
        $this->assertTrue(ContactNote::where('body', 'Lead moved to Interested')->exists());

        // Pipeline drag and drop.
        $this->postJson("/contacts/{$c->id}/lead", ['lead_status' => 'won'])->assertOk()->assertJson(['lead_status' => 'won']);
        $this->postJson("/contacts/{$c->id}/lead", ['lead_status' => 'bogus'])->assertStatus(422);
        $outsider = User::factory()->create();
        $this->postJson("/contacts/{$c->id}/lead", ['assigned_user_id' => $outsider->id])->assertStatus(422);
        $this->assertSame('won', $c->fresh()->lead_status);
    }

    public function test_pipeline_and_follow_ups(): void
    {
        $this->contact(['name' => 'Ravi Won', 'lead_status' => 'won', 'deal_value' => 300000]);
        $this->contact(['name' => 'Sita Lost', 'lead_status' => 'lost', 'deal_value' => 50000]);
        $late = $this->contact(['name' => 'Late Lead', 'lead_status' => 'interested', 'deal_value' => 100000, 'follow_up_at' => now()->subDays(2), 'follow_up_note' => 'Call back']);
        $this->contact(['name' => 'Future Lead', 'follow_up_at' => now()->addDays(3)]);

        $this->get('/crm/pipeline')->assertOk()->assertSeeInOrder(['New', 'Contacted', 'Interested', 'Late Lead', 'Negotiation', 'Won', 'Ravi Won', 'Lost', 'Sita Lost'])
            ->assertSee('₹3,00,000')->assertSee('50%');

        $this->get('/crm/follow-ups')->assertOk()->assertSeeInOrder(['Overdue', 'Late Lead', 'Call back', 'Today', 'Upcoming', 'Future Lead']);
        $this->post("/crm/follow-ups/{$late->id}/done")->assertRedirect();
        $this->assertNull($late->fresh()->follow_up_at);
        $this->assertTrue(ContactNote::where('body', 'Follow-up done: Call back')->exists());
    }

    public function test_filters_by_stage_tag_and_segment(): void
    {
        $this->contact(['name' => 'Hot Pune', 'tags' => ['hot lead', 'pune'], 'lead_status' => 'interested']);
        $this->contact(['name' => 'Cold Mumbai', 'tags' => ['mumbai']]);
        $this->contact(['name' => 'Blocked Guy', 'blocked_at' => now()]);

        $this->get('/contacts?stage=interested')->assertSee('Hot Pune')->assertDontSee('Cold Mumbai');
        $this->get('/contacts?tag=pune')->assertSee('Hot Pune')->assertDontSee('Cold Mumbai');
        $this->get('/contacts?segment=blacklisted')->assertSee('Blocked Guy')->assertDontSee('Hot Pune');
        $this->get('/contacts?segment=subscribed')->assertDontSee('Blocked Guy');
        $this->get('/contacts?q=mumbai')->assertDontSee('Hot Pune');
    }

    public function test_export_csv_with_custom_fields(): void
    {
        $this->contact(['phone' => '919811100001', 'name' => 'राम', 'fields' => ['city' => 'Pune'], 'tags' => ['vip'], 'lead_status' => 'won', 'deal_value' => 1000]);
        $this->contact(['phone' => '919811100002', 'name' => 'Other', 'lead_status' => 'new']);

        $csv = $this->get('/contacts/export?stage=won')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}phone,name,email,lead_status", $csv);
        $this->assertStringContainsString('city', $csv);
        $this->assertStringContainsString('919811100001,राम,,won,1000.00', $csv);
        $this->assertStringContainsString(',vip,', $csv);
        $this->assertStringNotContainsString('919811100002', $csv);

        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/contacts/export')->assertForbidden();
    }

    public function test_blacklist_blocks_every_kind_of_message(): void
    {
        config(['whatsapp.app_secret' => 'shh']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
        $account = WhatsappAccount::create(['waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 't', 'status' => 'connected']);
        $c = $this->contact(['phone' => '919822200001', 'last_inbound_at' => now(), 'last_message_at' => now()]);
        $ok = $this->contact(['phone' => '919822200002']);

        $this->post("/contacts/{$c->id}/block")->assertRedirect();
        $this->assertNotNull($c->fresh()->blocked_at);
        $this->assertSame([$ok->id], Contact::subscribed()->pluck('id')->all());

        // Live chat refuses.
        $this->postJson("/inbox/{$c->id}", ['body' => 'Hi'])->assertStatus(422);

        // Campaigns leave it out.
        $template = MessageTemplate::create(['whatsapp_account_id' => $account->id, 'name' => 'promo', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello']]]);
        $campaign = Campaign::create(['whatsapp_account_id' => $account->id, 'message_template_id' => $template->id, 'name' => 'Promo', 'status' => 'draft', 'audience' => ['all' => true]]);
        $this->assertSame(1, app(CampaignLauncher::class)->prepare($campaign));

        // Chatbot ignores it.
        Flow::create(['name' => 'Bot', 'triggers' => ['hi'], 'status' => 'active', 'definition' => FlowStarters::definition('blank')]);
        Http::fake();
        $value = ['metadata' => ['phone_number_id' => '555'], 'contacts' => [['profile' => ['name' => 'B'], 'wa_id' => '919822200001']],
            'messages' => [['from' => '919822200001', 'id' => 'wamid.IN1', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'hi']]]];
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh')], $body)->assertOk();
        Http::assertNothingSent();

        $this->post("/contacts/{$c->id}/block")->assertRedirect();
        $this->assertNull($c->fresh()->blocked_at);
    }

    public function test_campaign_message_skipped_if_blacklisted_after_prepare(): void
    {
        Http::fake();
        $account = WhatsappAccount::create(['waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 't', 'status' => 'connected']);
        $c = $this->contact(['phone' => '919833300001']);
        $template = MessageTemplate::create(['whatsapp_account_id' => $account->id, 'name' => 'promo', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello']]]);
        $campaign = Campaign::create(['whatsapp_account_id' => $account->id, 'message_template_id' => $template->id, 'name' => 'Promo', 'status' => 'draft', 'audience' => ['all' => true]]);
        app(CampaignLauncher::class)->prepare($campaign);
        $c->update(['blocked_at' => now()]);
        $campaign->update(['status' => 'running']);

        $msg = Message::where('campaign_id', $campaign->id)->firstOrFail();
        (new \App\Jobs\SendCampaignMessage($msg->id))->handle(app(CampaignLauncher::class));

        Http::assertNothingSent();
        $this->assertSame('failed', $msg->fresh()->status);
        $this->assertSame('Skipped: number is blacklisted', $msg->fresh()->error_message);
    }

    public function test_excel_import_and_custom_fields_form(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Phone', 'Name', 'City']));
        $writer->addRow(Row::fromValues([919876543210, 'Excel Ravi', 'Pune']));
        $writer->addRow(Row::fromValues(['9876543211', 'Excel Sita', 'Nashik']));
        $writer->close();

        $this->post('/contacts/import', [
            'file' => new UploadedFile($path, 'leads.xlsx', null, null, true),
            'new_group' => 'Excel leads', 'default_country_code' => '91', 'consent' => '1', 'opt_in_source' => 'expo',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $ravi = Contact::where('phone', '919876543210')->firstOrFail();
        $this->assertSame('Excel Ravi', $ravi->name);
        $this->assertSame('Pune', $ravi->fields['city']);
        $this->assertTrue(Contact::where('phone', '919876543211')->exists());

        $this->put("/contacts/{$ravi->id}", [
            'phone' => '919876543210', 'name' => 'Excel Ravi', 'tags' => 'vip, pune',
            'field_keys' => ['City', 'Budget Range', ''], 'field_values' => ['Mumbai', '50 lakh', ''],
        ])->assertRedirect("/contacts/{$ravi->id}");
        $ravi->refresh();
        $this->assertSame(['city' => 'Mumbai', 'budget_range' => '50 lakh'], $ravi->fields);
        $this->assertSame(['vip', 'pune'], $ravi->tags);

        $this->post('/contacts/import', ['file' => UploadedFile::fake()->create('x.pdf', 5), 'consent' => '1', 'opt_in_source' => 'x'])
            ->assertSessionHasErrors('file');
    }
}
