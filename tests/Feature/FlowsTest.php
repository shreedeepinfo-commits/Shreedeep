<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowSession;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\Flows\FlowDefinition;
use App\Services\Flows\FlowStarters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlowsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.app_secret' => 'shh', 'whatsapp.graph_version' => 'v24.0']);
        $this->user = User::factory()->create();
        $ws = $this->user->createWorkspace('Homes')->id;
        $this->account = WhatsappAccount::create([
            'workspace_id' => $ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'display_phone_number' => '+91 90000 00000',
        ]);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
    }

    private function liveFlow(string $starter = 'real_estate', array $triggers = ['hi', 'property']): Flow
    {
        return Flow::withoutGlobalScopes()->create([
            'workspace_id' => $this->account->workspace_id, 'name' => 'Real estate', 'triggers' => $triggers,
            'status' => 'active', 'definition' => FlowStarters::definition($starter),
        ]);
    }

    private function reply(array $message)
    {
        $value = [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919000000000', 'phone_number_id' => '555'],
            'contacts' => [['profile' => ['name' => 'Ravi'], 'wa_id' => '919811111111']],
            'messages' => [array_merge(['from' => '919811111111', 'id' => 'wamid.IN'.uniqid(), 'timestamp' => (string) time()], $message)],
        ];
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh'),
        ], $body)->assertOk();
    }

    private function text(string $t)
    {
        return $this->reply(['type' => 'text', 'text' => ['body' => $t]]);
    }

    private function button(string $id, string $title)
    {
        return $this->reply(['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $id, 'title' => $title]]]);
    }

    private function sentPayloads(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    public function test_starters_are_valid(): void
    {
        foreach (array_keys(FlowStarters::all()) as $key) {
            $this->assertSame([], FlowDefinition::errors(FlowStarters::definition($key)), $key);
        }
    }

    public function test_invalid_definitions_are_reported(): void
    {
        $this->assertNotEmpty(FlowDefinition::errors(['start' => 'x', 'nodes' => []]));
        $tooMany = ['start' => 'a', 'nodes' => ['a' => ['type' => 'buttons', 'text' => 'Pick', 'options' => [
            ['title' => 'One'], ['title' => 'Two'], ['title' => 'Three'], ['title' => 'Four'],
        ]]]];
        $this->assertNotEmpty(FlowDefinition::errors($tooMany));
        $longTitle = ['start' => 'a', 'nodes' => ['a' => ['type' => 'buttons', 'text' => 'Pick', 'options' => [['title' => str_repeat('x', 21)]]]]];
        $this->assertNotEmpty(FlowDefinition::errors($longTitle));
        $badNext = ['start' => 'a', 'nodes' => ['a' => ['type' => 'message', 'text' => 'Hi', 'next' => 'missing']]];
        $this->assertNotEmpty(FlowDefinition::errors($badNext));
    }

    public function test_real_estate_flow_runs_end_to_end(): void
    {
        $flow = $this->liveFlow();

        $this->text('Hi');
        $first = $this->sentPayloads()[0];
        $this->assertSame('interactive', $first['type']);
        $this->assertSame('button', $first['interactive']['type']);
        $this->assertSame(['Sales', 'Support'], array_column(array_column($first['interactive']['action']['buttons'], 'reply'), 'title'));
        $this->assertSame('welcome:opt0', $first['interactive']['action']['buttons'][0]['reply']['id']);

        $this->button('welcome:opt0', 'Sales');
        $this->assertStringContainsString('Which city', collect($this->sentPayloads())->last()['interactive']['body']['text'] ?? '');

        $this->text('pune'); // typed instead of tapped
        $list = collect($this->sentPayloads())->last();
        $this->assertSame('list', $list['interactive']['type']);

        $this->reply(['type' => 'interactive', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'property:opt1', 'title' => 'Villa']]]);
        $this->text('80 lakh');

        $contact = Contact::withoutGlobalScopes()->where('phone', '919811111111')->first();
        $this->assertSame('80 lakh', $contact->fields['budget']);

        $session = FlowSession::withoutGlobalScopes()->first();
        $this->assertSame('completed', $session->status);
        $this->assertSame(['welcome' => 'Sales', 'city' => 'Pune', 'property' => 'Villa', 'budget' => '80 lakh'], $session->answers);

        $flow->refresh();
        $this->assertSame(1, $flow->triggered_count);
        $this->assertSame(1, $flow->completed_count);
        $this->assertStringContainsString('Thank you', collect($this->sentPayloads())->last()['text']['body']);
    }

    public function test_wrong_answer_asks_again(): void
    {
        $this->liveFlow();
        $this->text('hi');
        $this->text('something else');

        $last = collect($this->sentPayloads())->last();
        $this->assertSame('interactive', $last['type']);
        $this->assertStringContainsString('choose one of the options', $last['interactive']['body']['text']);
    }

    public function test_paused_flow_and_unknown_text_do_nothing(): void
    {
        $flow = $this->liveFlow();
        $flow->update(['status' => 'draft']);
        $this->text('hi');
        $this->assertCount(0, Http::recorded());
    }

    public function test_flows_from_other_workspaces_never_fire(): void
    {
        $other = User::factory()->create()->createWorkspace('Other');
        Flow::withoutGlobalScopes()->create([
            'workspace_id' => $other->id, 'name' => 'Theirs', 'triggers' => ['hi'], 'status' => 'active', 'definition' => FlowStarters::definition('store'),
        ]);
        $this->text('hi');
        $this->assertCount(0, Http::recorded());
    }

    public function test_builder_crud_and_trigger_clash(): void
    {
        $this->actingAs($this->user);
        $this->get('/flows')->assertOk()->assertSee('Real estate enquiry');
        $this->get('/flows/create?starter=real_estate')->assertOk()->assertSee('Sales');

        $payload = fn (array $o = []) => array_merge([
            'name' => 'Homes bot', 'triggers' => 'hi, property', 'status' => 'active',
            'definition' => json_encode(FlowStarters::definition('real_estate')),
        ], $o);

        $this->post('/flows', $payload())->assertRedirect();
        $flow = Flow::firstOrFail();
        $this->assertSame(['hi', 'property'], $flow->triggers);
        $this->assertSame('active', $flow->status);
        $this->get("/flows/{$flow->id}/edit")->assertOk()->assertSee('Homes bot');

        // A second live flow with the same keyword is refused; as a draft it is fine.
        $this->post('/flows', $payload(['name' => 'Copy', 'triggers' => 'HI']))->assertSessionHasErrors('triggers');
        $this->post('/flows', $payload(['name' => 'Copy', 'triggers' => 'HI', 'status' => 'draft']))->assertSessionHasNoErrors();

        // Broken definitions are refused.
        $this->post('/flows', $payload(['name' => 'Bad', 'triggers' => 'bad', 'definition' => json_encode(['start' => 'nope', 'nodes' => []])]))
            ->assertSessionHasErrors('definition');

        $this->post("/flows/{$flow->id}/toggle")->assertRedirect();
        $this->assertSame('draft', $flow->fresh()->status);
        $this->post("/flows/{$flow->id}/duplicate")->assertRedirect();
        $this->assertSame(3, Flow::count());
        $this->delete("/flows/{$flow->id}")->assertRedirect('/flows');
        $this->assertSame(2, Flow::count());
    }

    public function test_other_workspace_flow_is_not_reachable(): void
    {
        $other = User::factory()->create();
        $ws = $other->createWorkspace('Other');
        $flow = Flow::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id, 'name' => 'Theirs', 'triggers' => ['hi'], 'status' => 'draft', 'definition' => FlowStarters::definition('blank'),
        ]);
        $this->actingAs($this->user)->get("/flows/{$flow->id}/edit")->assertNotFound();
        $this->actingAs($this->user)->delete("/flows/{$flow->id}")->assertNotFound();
    }
}
