<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Get started');
    }

    public function test_registration_creates_user_and_owned_workspace(): void
    {
        $this->post('/register', [
            'name' => 'Dilip',
            'company' => 'Acme Traders',
            'email' => 'owner@example.com',
            'password' => 'secret-password-123',
            'password_confirmation' => 'secret-password-123',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Acme Traders', $user->currentWorkspace->name);
        $this->assertSame('owner', $user->workspaces->first()->pivot->role);
    }

    public function test_login_and_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-123']);
        $user->createWorkspace('Shop');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password-123'])
            ->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee('Shop');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_without_workspace_gets_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertNotNull($user->fresh()->current_workspace_id);
    }
}
