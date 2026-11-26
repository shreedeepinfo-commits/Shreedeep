<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.graph_version' => 'v24.0']);
        $this->user = User::factory()->create();
        $this->user->createWorkspace('Shop');
        $this->account = WhatsappAccount::create([
            'workspace_id' => $this->user->current_workspace_id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok',
        ]);
    }

    public function test_sync_pages_through_meta_templates(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/999/message_templates*' => Http::sequence()
                ->push(['data' => [['id' => '1', 'name' => 'hello_world', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'UTILITY',
                    'components' => [['type' => 'BODY', 'text' => 'Hello World']]]], 'paging' => ['cursors' => ['after' => 'C1'], 'next' => 'https://x']])
                ->push(['data' => [['id' => '2', 'name' => 'offer', 'language' => 'hi', 'status' => 'REJECTED', 'category' => 'MARKETING', 'rejected_reason' => 'INVALID_FORMAT',
                    'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}} off']]]], 'paging' => ['cursors' => ['after' => 'C2']]]),
        ]);

        $this->actingAs($this->user)->post('/templates/sync')->assertSessionHas('status', 'Synced 2 templates from Meta.');

        $this->assertSame(2, MessageTemplate::count());
        $offer = MessageTemplate::where('name', 'offer')->first();
        $this->assertSame(2, $offer->bodyVariableCount());
        $this->assertSame('Hi Ravi, 20% off', $offer->render(['Ravi', '20%']));
        $this->assertSame('INVALID_FORMAT', $offer->rejected_reason);
        Http::assertSent(fn ($r) => ($r->data()['after'] ?? null) === 'C1' || str_contains($r->url(), 'after=C1'));
        $this->get('/templates')->assertSee('hello_world')->assertSee('REJECTED');
    }

    public function test_create_template_submits_components_with_examples(): void
    {
        Http::fake(['*/999/message_templates' => Http::response(['id' => '77', 'status' => 'PENDING', 'category' => 'MARKETING'])]);

        $this->actingAs($this->user)->post('/templates', [
            'whatsapp_account_id' => $this->account->id,
            'name' => 'diwali_offer',
            'language' => 'hi',
            'category' => 'MARKETING',
            'body' => 'Hi {{1}}, get {{2}} off',
            'body_examples' => ['Ravi', '20%'],
            'footer' => 'Reply STOP to unsubscribe',
            'buttons' => [['type' => 'URL', 'text' => 'Shop now', 'value' => 'https://example.com'], ['type' => 'QUICK_REPLY', 'text' => '']],
        ])->assertRedirect('/templates?t=1');

        Http::assertSent(function ($r) {
            $body = collect($r['components'])->firstWhere('type', 'BODY');
            $buttons = collect($r['components'])->firstWhere('type', 'BUTTONS');

            return $r['name'] === 'diwali_offer' && $body['example']['body_text'] === [['Ravi', '20%']] && count($buttons['buttons']) === 1;
        });
        $this->assertSame('PENDING', MessageTemplate::first()->status);
    }

    public function test_invalid_template_name_rejected(): void
    {
        Http::fake();
        $this->actingAs($this->user)->post('/templates', [
            'whatsapp_account_id' => $this->account->id, 'name' => 'Bad Name', 'language' => 'en', 'category' => 'MARKETING', 'body' => 'x',
        ])->assertSessionHasErrors('name');
        Http::assertNothingSent();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->user)->get('/templates/create')->assertOk()->assertSee('Submit to Meta for approval');
    }
}
