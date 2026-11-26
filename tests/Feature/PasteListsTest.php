<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\PastedNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PasteListsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->user = User::factory()->create();
        $this->user->createWorkspace('Shop');
    }

    public function test_parser_handles_lines_names_and_comma_lists(): void
    {
        $rows = PastedNumbers::parse("9876543210\n9876543211, Ravi Kumar\n\n+91 98765 43212\tSita\n9876543213,9876543214; 9876543215\nhello world");
        $this->assertSame([
            ['9876543210', null], ['9876543211', 'Ravi Kumar'], ['+91 98765 43212', 'Sita'],
            ['9876543213', null], ['9876543214', null], ['9876543215', null],
        ], $rows);
    }

    public function test_paste_creates_named_list_and_dedupes(): void
    {
        $this->actingAs($this->user)->post('/contacts/paste', [
            'list_name' => 'Dilip',
            'numbers' => "9876543210\n9876543211, Ravi\n+919876543210\n123",
            'default_country_code' => '91',
            'opt_in_source' => 'shop counter',
            'consent' => '1',
        ])->assertRedirect();

        $group = ContactGroup::where('name', 'Dilip')->firstOrFail();
        $this->assertSame(2, $group->contacts()->count());
        $this->assertSame('Ravi', Contact::where('phone', '919876543211')->value('name'));
        $this->get('/contacts?group='.$group->id)->assertSee('Dilip')->assertSee('+919876543211');
    }

    public function test_paste_twenty_thousand_numbers(): void
    {
        $numbers = implode("\n", array_map(fn ($i) => (string) (9000000000 + $i), range(1, 20000)));
        $start = microtime(true);

        $this->actingAs($this->user)->post('/contacts/paste', [
            'list_name' => 'Big list', 'numbers' => $numbers, 'default_country_code' => '91', 'opt_in_source' => 'x', 'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(20000, ContactGroup::where('name', 'Big list')->first()->contacts()->count());
        $this->assertLessThan(60, microtime(true) - $start);
    }

    public function test_campaign_with_pasted_numbers(): void
    {
        Http::fake(['*/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
        $account = WhatsappAccount::create(['workspace_id' => $this->user->current_workspace_id, 'waba_id' => '1', 'phone_number_id' => '555', 'access_token' => 't']);
        $template = MessageTemplate::create([
            'workspace_id' => $this->user->current_workspace_id, 'whatsapp_account_id' => $account->id, 'name' => 'hello_world',
            'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello']],
        ]);

        $this->actingAs($this->user)->post('/campaigns', [
            'name' => 'Quick blast', 'message_template_id' => $template->id, 'audience' => 'paste',
            'numbers' => "9876543210\n9876543211", 'default_country_code' => '91', 'consent' => '1', 'send' => 'now',
        ])->assertRedirect();

        $campaign = Campaign::firstOrFail();
        $this->assertSame(2, $campaign->total);
        $this->assertTrue(ContactGroup::where('name', 'Quick blast list')->exists());
        Http::assertSentCount(2);
    }

    public function test_paste_campaign_requires_consent(): void
    {
        $account = WhatsappAccount::create(['workspace_id' => $this->user->current_workspace_id, 'waba_id' => '1', 'phone_number_id' => '555', 'access_token' => 't']);
        $template = MessageTemplate::create([
            'workspace_id' => $this->user->current_workspace_id, 'whatsapp_account_id' => $account->id, 'name' => 'hello_world',
            'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello']],
        ]);
        $this->actingAs($this->user)->post('/campaigns', [
            'name' => 'X', 'message_template_id' => $template->id, 'audience' => 'paste', 'numbers' => '9876543210', 'send' => 'now',
        ])->assertSessionHasErrors('consent');
    }
}
