<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $company = 'Shop'): User
    {
        $user = User::factory()->create();
        $user->createWorkspace($company);

        return $user;
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('919876543210', Phone::normalize('98765 43210', '91'));
        $this->assertSame('919876543210', Phone::normalize('09876543210', '91'));
        $this->assertSame('919876543210', Phone::normalize('+91 98765-43210', '1'));
        $this->assertSame('14155550100', Phone::normalize('0014155550100', '91'));
        $this->assertNull(Phone::normalize('12345', '91'));
        $this->assertNull(Phone::normalize('', '91'));
    }

    public function test_add_and_list_contact(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/contacts', ['phone' => '98765 43210', 'name' => 'Ravi'])->assertRedirect('/contacts');
        $this->get('/contacts')->assertOk()->assertSee('+919876543210')->assertSee('Ravi');

        $this->post('/contacts', ['phone' => '+919876543210'])->assertSessionHasErrors('phone');
    }

    public function test_csv_import_into_new_group_with_extra_columns(): void
    {
        Storage::fake();
        $user = $this->owner();
        $csv = "Name,Mobile,City\nRavi,9876543210,Pune\nSita,+91 99999 88888,Delhi\nBad,123,X\nRavi again,09876543210,Mumbai\n";

        $this->actingAs($user)->post('/contacts/import', [
            'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
            'new_group' => 'Diwali',
            'default_country_code' => '91',
            'opt_in_source' => 'website form',
            'consent' => '1',
        ])->assertRedirect('/contacts');

        $this->assertSame(2, Contact::count());
        $ravi = Contact::where('phone', '919876543210')->first();
        $this->assertSame('Ravi again', $ravi->name);
        $this->assertSame('Mumbai', $ravi->variable('city'));
        $this->assertSame('website form', $ravi->opt_in_source);
        $this->assertSame(2, ContactGroup::where('name', 'Diwali')->first()->contacts()->count());
        $this->get('/contacts')->assertSee('1 skipped');
    }

    public function test_import_requires_consent(): void
    {
        $this->actingAs($this->owner())->post('/contacts/import', [
            'file' => UploadedFile::fake()->createWithContent('list.csv', "phone\n9876543210\n"),
            'opt_in_source' => 'x',
        ])->assertSessionHasErrors('consent');
    }

    public function test_contacts_are_isolated_between_workspaces(): void
    {
        $a = $this->owner('A');
        $this->actingAs($a)->post('/contacts', ['phone' => '919876543210', 'name' => 'Secret']);
        $contact = Contact::withoutGlobalScopes()->first();

        $b = $this->owner('B');
        $this->actingAs($b)->get('/contacts')->assertDontSee('Secret');
        $this->get("/contacts/{$contact->id}/edit")->assertNotFound();
        $this->delete("/contacts/{$contact->id}")->assertNotFound();
        // Same number can exist in another workspace.
        $this->post('/contacts', ['phone' => '919876543210'])->assertSessionHasNoErrors();
    }
}
