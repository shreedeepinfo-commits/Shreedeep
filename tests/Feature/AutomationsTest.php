<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationEnrollment;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $ws;

    private WhatsappAccount $account;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.app_secret' => 'shh', 'whatsapp.graph_version' => 'v24.0', 'queue.default' => 'sync']);
        $this->user = User::factory()->create();
        $this->ws = $this->user->createWorkspace('Homes')->id;
        $this->account = WhatsappAccount::create([
            'workspace_id' => $this->ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'display_phone_number' => '+91 90000 00000',
        ]);
        $this->template = MessageTemplate::create([
            'workspace_id' => $this->ws, 'whatsapp_account_id' => $this->account->id, 'name' => 'hello_offer', 'language' => 'en',
            'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, happy day!']],
        ]);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
    }

    private function automation(string $type, array $config, array $extra = []): Automation
    {
        return Automation::withoutGlobalScopes()->create(['workspace_id' => $this->ws, 'type' => $type, 'name' => $type, 'active' => true, 'config' => $config] + $extra);
    }

    private function text(string $t, string $from = '919811111111')
    {
        $value = [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919000000000', 'phone_number_id' => '555'],
            'contacts' => [['profile' => ['name' => 'Ravi'], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => 'wamid.IN'.uniqid(), 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => $t]]],
        ];
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh'),
        ], $body)->assertOk();
    }

    private function sentTexts(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0]->data())->where('type', 'text')->pluck('text.body')->values()->all();
    }

    private function sentTemplates(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0]->data())->where('type', 'template')->values()->all();
    }

    private function alwaysOpen(): array
    {
        return collect(Automation::DAYS)->mapWithKeys(fn ($l, $d) => [$d => ['open' => true, 'from' => '00:00', 'to' => '23:59']])->all();
    }

    public function test_welcome_goes_only_on_the_first_message(): void
    {
        $this->automation('welcome', ['message' => 'Hi {{name}}, welcome!']);

        $this->text('hello');
        $this->text('hello again');

        $this->assertSame(['Hi Ravi, welcome!'], $this->sentTexts());
        $this->assertSame(1, Automation::withoutGlobalScopes()->first()->sent_count);
        $this->assertSame(1, Message::withoutGlobalScopes()->where('direction', 'out')->count());
    }

    public function test_keyword_reply_matches_whole_words(): void
    {
        $this->automation('keyword', ['keywords' => ['price', 'rate'], 'match' => 'contains', 'reply' => 'Our price list: …']);
        $this->automation('keyword', ['keywords' => ['address'], 'match' => 'exact', 'reply' => 'We are at MG Road.']);

        $this->text('What is the PRICE?');
        $this->text('accurate');               // "rate" inside a word: no reply
        $this->text('send address please');    // exact match only: no reply
        $this->text('Address');

        $this->assertSame(['Our price list: …', 'We are at MG Road.'], $this->sentTexts());
    }

    public function test_away_message_outside_hours_once_per_window(): void
    {
        $closed = collect(Automation::DAYS)->mapWithKeys(fn ($l, $d) => [$d => ['open' => false, 'from' => '10:00', 'to' => '19:00']])->all();
        $away = $this->automation('away', ['message' => 'We are closed.', 'timezone' => 'Asia/Kolkata', 'hours' => $closed]);

        $this->text('hi');
        $this->text('anyone there?');
        $this->assertSame(['We are closed.'], $this->sentTexts());

        $away->update(['config' => ['message' => 'We are closed.', 'timezone' => 'Asia/Kolkata', 'hours' => $this->alwaysOpen()]]);
        $this->text('hi', '919822222222');
        $this->assertCount(1, $this->sentTexts());
    }

    public function test_business_hours_use_the_timezone(): void
    {
        $a = new Automation(['config' => ['timezone' => 'Asia/Kolkata', 'hours' => ['mon' => ['open' => true, 'from' => '10:00', 'to' => '19:00']]]]);

        $this->assertTrue($a->isOpenAt(\Carbon\Carbon::parse('2026-11-23 05:00', 'UTC')));   // Mon 10:30 IST
        $this->assertFalse($a->isOpenAt(\Carbon\Carbon::parse('2026-11-23 14:00', 'UTC')));  // Mon 19:30 IST
        $this->assertFalse($a->isOpenAt(\Carbon\Carbon::parse('2026-11-24 05:00', 'UTC')));  // Tuesday: not set
    }

    public function test_blocked_contacts_and_opt_out_get_no_automatic_reply(): void
    {
        $this->automation('welcome', ['message' => 'Welcome!']);
        $this->automation('keyword', ['keywords' => ['stop'], 'match' => 'exact', 'reply' => 'Never sent']);
        Contact::create(['workspace_id' => $this->ws, 'phone' => '919833333333', 'blocked_at' => now()]);

        $this->text('hi', '919833333333');
        $this->text('stop', '919844444444');

        $this->assertSame([], $this->sentTexts());
    }

    public function test_drip_sends_steps_in_order_and_skips_opted_out(): void
    {
        $group = ContactGroup::create(['workspace_id' => $this->ws, 'name' => 'Leads']);
        $ravi = Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000001', 'name' => 'Ravi']);
        $gone = Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000002', 'opted_out_at' => now()]);
        $other = Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000003']);
        $group->contacts()->attach([$ravi->id, $gone->id]);

        $step = ['template_id' => $this->template->id, 'variables' => ['body' => [['type' => 'name', 'fallback' => 'friend']]]];
        $drip = $this->automation('drip', [
            'audience' => ['groups' => [$group->id], 'tags' => []], 'enroll_new' => true,
            'steps' => [$step + ['delay_days' => 0, 'delay_hours' => 0], $step + ['delay_days' => 2, 'delay_hours' => 0]],
        ]);

        $this->artisan('automations:run')->assertSuccessful();
        $this->assertCount(1, $this->sentTemplates());
        $this->assertSame('Ravi', $this->sentTemplates()[0]['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame(1, AutomationEnrollment::withoutGlobalScopes()->count());

        $this->artisan('automations:run');
        $this->assertCount(1, $this->sentTemplates()); // second step waits 2 days

        $this->travel(2)->days();
        $this->travel(1)->minutes();
        $this->artisan('automations:run');
        $this->assertCount(2, $this->sentTemplates());
        $this->assertSame('done', AutomationEnrollment::withoutGlobalScopes()->first()->status);
        $this->assertSame(2, $drip->fresh()->sent_count);
        $this->assertSame(2, Message::withoutGlobalScopes()->where('contact_id', $ravi->id)->where('type', 'template')->count());
        $this->assertSame(0, Message::withoutGlobalScopes()->whereIn('contact_id', [$gone->id, $other->id])->count());
    }

    public function test_birthday_reminder_sends_once_on_the_day(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-15 04:00', 'UTC')); // 09:30 IST
        Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000001', 'name' => 'Asha', 'fields' => ['birthday' => '15-08-1990']]);
        Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000002', 'name' => 'Raj', 'fields' => ['birthday' => '1988-08-16']]);
        Contact::create(['workspace_id' => $this->ws, 'phone' => '919800000003', 'name' => 'Mo', 'fields' => ['birthday' => 'unknown']]);
        $this->automation('date', [
            'field' => 'birthday', 'days_before' => 0, 'time' => '09:00', 'yearly' => true, 'timezone' => 'Asia/Kolkata',
            'template_id' => $this->template->id, 'variables' => ['body' => [['type' => 'name']]],
        ]);

        $this->artisan('automations:run');
        $this->artisan('automations:run');

        $sent = $this->sentTemplates();
        $this->assertCount(1, $sent);
        $this->assertSame('919800000001', $sent[0]['to']);
        $this->assertSame([2000 => null][2000], Automation::parseDate('15/08')[0]);
        $this->assertSame([1990, 8, 15], Automation::parseDate('1990-08-15'));
        $this->assertNull(Automation::parseDate('31-02-1990'));
    }

    public function test_manager_can_create_edit_toggle_and_delete(): void
    {
        $this->actingAs($this->user);
        $this->get('/automations')->assertOk()->assertSee('Automations');
        $this->get('/automations/create?type=drip')->assertOk()->assertSee('Steps');

        $this->post('/automations', [
            'type' => 'keyword', 'name' => 'Price', 'active' => 1, 'keywords' => "price, rate\ncost", 'match' => 'contains', 'reply' => 'Price list',
        ])->assertRedirect('/automations');
        $kw = Automation::where('type', 'keyword')->firstOrFail();
        $this->assertSame(['price', 'rate', 'cost'], $kw->config['keywords']);

        $this->post('/automations', [
            'type' => 'away', 'name' => 'After hours', 'active' => 1, 'message' => 'Closed', 'timezone' => 'Asia/Kolkata',
            'hours' => ['mon' => ['open' => 1, 'from' => '09:00', 'to' => '18:00'], 'tue' => ['open' => 0, 'from' => '09:00', 'to' => '18:00']],
        ])->assertRedirect('/automations');
        $away = Automation::where('type', 'away')->firstOrFail();
        $this->assertTrue($away->config['hours']['mon']['open']);
        $this->assertFalse($away->config['hours']['sun']['open']);
        $this->get('/automations/create?type=away')->assertRedirect("/automations/{$away->id}/edit");

        $this->post('/automations', [
            'type' => 'drip', 'name' => 'Nurture', 'active' => 1, 'enroll_new' => 1, 'tags' => 'lead',
            'steps' => [5 => ['template_id' => $this->template->id, 'delay_days' => 0, 'delay_hours' => 1, 'variables' => ['body' => [['type' => 'name']]]]],
        ])->assertRedirect('/automations');
        $drip = Automation::where('type', 'drip')->firstOrFail();
        $this->assertSame(['lead'], $drip->config['audience']['tags']);
        $this->assertSame(1, $drip->config['steps'][0]['delay_hours']);
        $this->get("/automations/{$drip->id}/edit")->assertOk();

        $this->post('/automations', ['type' => 'date', 'name' => 'B', 'field' => 'birthday', 'days_before' => 0, 'time' => '09:00', 'timezone' => 'Asia/Kolkata'])
            ->assertSessionHasErrors('template_id');

        $this->put("/automations/{$kw->id}", ['name' => 'Prices', 'keywords' => 'price', 'match' => 'exact', 'reply' => 'New'])->assertRedirect('/automations');
        $this->assertFalse($kw->fresh()->active);
        $this->post("/automations/{$kw->id}/toggle")->assertRedirect();
        $this->assertTrue($kw->fresh()->active);
        $this->delete("/automations/{$kw->id}")->assertRedirect('/automations');
        $this->assertSame(0, Automation::where('type', 'keyword')->count());
        $this->get('/automations')->assertOk()->assertSee('Nurture');
    }

    public function test_agents_and_other_workspaces_cannot_touch_automations(): void
    {
        $mine = $this->automation('keyword', ['keywords' => ['x'], 'match' => 'exact', 'reply' => 'y']);
        $other = User::factory()->create();
        $other->createWorkspace('Other');
        $this->actingAs($other)->get("/automations/{$mine->id}/edit")->assertNotFound();

        $agent = User::factory()->create();
        \App\Models\Workspace::find($this->ws)->users()->attach($agent->id, ['role' => 'agent']);
        $this->actingAs($agent)->withSession(['workspace_id' => $this->ws])->get('/automations')->assertForbidden();
    }
}
