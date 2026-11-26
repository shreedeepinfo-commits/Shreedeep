<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplatesProTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.graph_version' => 'v24.0', 'whatsapp.graph_url' => 'https://graph.facebook.com', 'whatsapp.app_id' => 'APP1']);
        $this->user = User::factory()->create();
        $ws = $this->user->createWorkspace('Shop')->id;
        $this->account = WhatsappAccount::create(['workspace_id' => $ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'status' => 'connected']);
        Http::fake([
            'graph.facebook.com/v24.0/APP1/uploads*' => Http::response(['id' => 'upload:abc']),
            'graph.facebook.com/v24.0/upload:abc' => Http::response(['h' => 'HANDLE-1']),
            'graph.facebook.com/v24.0/999/message_templates' => Http::response(['id' => 'T100', 'status' => 'PENDING', 'category' => 'MARKETING']),
            'graph.facebook.com/v24.0/T55' => Http::response(['success' => true]),
            'graph.facebook.com/v24.0/555/messages' => Http::response(['messages' => [['id' => 'wamid.test']]]),
        ]);
        $this->actingAs($this->user);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'whatsapp_account_id' => $this->account->id, 'name' => 'promo_one', 'language' => 'hi', 'category' => 'MARKETING',
            'header_type' => 'none', 'body' => 'Hi {{1}}, sale is live', 'body_examples' => ['Ravi'], 'footer' => 'Reply STOP',
            'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Interested', 'value' => '', 'example' => '']],
        ], $o);
    }

    private function sentTemplate(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])->first(fn ($r) => str_ends_with($r->url(), '/999/message_templates'))->data();
    }

    public function test_image_header_uploads_sample_and_all_button_types(): void
    {
        $this->post('/templates', $this->payload([
            'header_type' => 'image', 'header_file' => UploadedFile::fake()->image('offer.jpg'),
            'buttons' => [
                ['type' => 'URL', 'text' => 'Shop now', 'value' => 'https://shop.example/p/{{1}}', 'example' => 'diwali'],
                ['type' => 'PHONE_NUMBER', 'text' => 'Call us', 'value' => '+91 98765 43210', 'example' => ''],
                ['type' => 'COPY_CODE', 'text' => '', 'value' => 'DIWALI20', 'example' => ''],
                ['type' => 'QUICK_REPLY', 'text' => 'Stop', 'value' => '', 'example' => ''],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $components = collect($this->sentTemplate()['components'])->keyBy('type');
        $this->assertSame(['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['HANDLE-1']]], $components['HEADER']);
        $this->assertSame([
            ['type' => 'URL', 'text' => 'Shop now', 'url' => 'https://shop.example/p/{{1}}', 'example' => ['diwali']],
            ['type' => 'PHONE_NUMBER', 'text' => 'Call us', 'phone_number' => '+919876543210'],
            ['type' => 'COPY_CODE', 'example' => 'DIWALI20'],
            ['type' => 'QUICK_REPLY', 'text' => 'Stop'],
        ], $components['BUTTONS']['buttons']);
        Http::assertSent(fn ($r) => $r->url() === 'https://graph.facebook.com/v24.0/upload:abc' && $r->header('Authorization')[0] === 'OAuth tok');
    }

    public function test_media_header_needs_a_sample_and_button_rules(): void
    {
        $this->post('/templates', $this->payload(['header_type' => 'video']))->assertSessionHasErrors('header_file');
        $this->post('/templates', $this->payload(['buttons' => [
            ['type' => 'URL', 'text' => 'A', 'value' => 'https://a.in'], ['type' => 'URL', 'text' => 'B', 'value' => 'https://b.in'], ['type' => 'URL', 'text' => 'C', 'value' => 'https://c.in'],
        ]]))->assertSessionHasErrors('buttons');
        $this->post('/templates', $this->payload(['buttons' => [['type' => 'URL', 'text' => 'A', 'value' => 'shop.in']]]))->assertSessionHasErrors('buttons');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/message_templates'));
    }

    public function test_authentication_template(): void
    {
        $this->post('/templates', $this->payload([
            'name' => 'login_otp', 'category' => 'AUTHENTICATION', 'body' => '', 'security_recommendation' => '1',
            'code_expiration_minutes' => 5, 'otp_button_text' => 'Copy code',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([
            ['type' => 'BODY', 'add_security_recommendation' => true],
            ['type' => 'FOOTER', 'code_expiration_minutes' => 5],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copy code']]],
        ], $this->sentTemplate()['components']);
        $this->assertSame('AUTHENTICATION', $this->sentTemplate()['category']);
    }

    public function test_edit_rejected_template_and_duplicate(): void
    {
        $template = MessageTemplate::create([
            'workspace_id' => $this->account->workspace_id, 'whatsapp_account_id' => $this->account->id, 'meta_template_id' => 'T55', 'name' => 'old_offer', 'language' => 'en',
            'category' => 'MARKETING', 'status' => 'REJECTED', 'rejected_reason' => 'PROMOTIONAL',
            'components' => [['type' => 'BODY', 'text' => 'Buy {{1}} now', 'example' => ['body_text' => [['shoes']]]], ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Yes']]]],
        ]);

        $this->get("/templates/{$template->id}/edit")->assertOk()->assertSee('Meta rejected it')->assertSee('Buy {{1}} now')->assertSee('value="Yes"', false);
        $this->put("/templates/{$template->id}", $this->payload(['body' => 'Hello {{1}}, new arrivals', 'body_examples' => ['Asha'], 'name' => 'hacked_name']))
            ->assertRedirect("/templates?t={$template->id}");

        $template->refresh();
        $this->assertSame('PENDING', $template->status);
        $this->assertSame('old_offer', $template->name);
        $this->assertNull($template->rejected_reason);
        Http::assertSent(fn ($r) => $r->url() === 'https://graph.facebook.com/v24.0/T55' && $r['components'][0]['text'] === 'Hello {{1}}, new arrivals');

        $this->get("/templates/create?from={$template->id}")->assertOk()->assertSee('old_offer_copy');

        $template->update(['status' => 'PENDING']);
        $this->get("/templates/{$template->id}/edit")->assertForbidden();
    }

    public function test_send_test_message(): void
    {
        $template = MessageTemplate::create([
            'workspace_id' => $this->account->workspace_id, 'whatsapp_account_id' => $this->account->id, 'name' => 'promo', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}} off']],
        ]);

        $this->get("/templates?t={$template->id}")->assertSee('Send a test');
        $this->post("/templates/{$template->id}/test", ['phone' => '9876543210', 'values' => ['Ravi']])->assertRedirect()->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/555/messages') && $r['to'] === '919876543210'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Ravi'
            && $r['template']['components'][0]['parameters'][1]['text'] === 'Test 2');

        $this->post("/templates/{$template->id}/test", ['phone' => '12'])->assertSessionHasErrors('phone');
    }

    public function test_form_data_round_trip(): void
    {
        $data = $this->payload(['header_type' => 'text', 'header' => 'Big {{1}}', 'header_example' => 'Sale',
            'buttons' => [['type' => 'URL', 'text' => 'Go', 'value' => 'https://x.in/{{1}}', 'example' => 'abc']]]);
        $template = new MessageTemplate(['components' => TemplateService::buildComponents($data), 'category' => 'MARKETING']);
        $form = TemplateService::formData($template);
        $this->assertSame(['text', 'Big {{1}}', 'Sale', 'Hi {{1}}, sale is live', ['Ravi']], [$form['header_type'], $form['header'], $form['header_example'], $form['body'], $form['body_examples']]);
        $this->assertSame(['type' => 'URL', 'text' => 'Go', 'value' => 'https://x.in/{{1}}', 'example' => 'abc'], $form['buttons'][0]);
    }
}
