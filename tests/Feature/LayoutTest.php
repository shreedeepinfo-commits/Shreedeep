<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_brand_sidebar_status_and_checklist(): void
    {
        $user = User::factory()->create(['name' => 'Dilip Kumar']);
        $user->createWorkspace('Shree Deep');

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Shree Deep Infotech')
            ->assertSee('WhatsApp Business Suite')
            ->assertSeeInOrder(['Dashboard', 'Live Chat', 'Campaigns', 'Templates', 'Contacts &amp; CRM', 'Chatbot Builder', 'Automations',
                'WhatsApp Flows', 'Integrations', 'API &amp; Webhooks', 'Team Management', 'Analytics', 'Subscription',
                'WhatsApp Settings', 'Help &amp; Support'], false)
            ->assertSee('Meta not connected')
            ->assertSee('Active Chatbots')
            ->assertSee('Ready to reach your customers?')
            ->assertSee('Not connected')
            ->assertSee('0 of 5 done');
    }

    public function test_coming_soon_pages(): void
    {
        $user = User::factory()->create();
        $user->createWorkspace('Shop');

        foreach (array_keys(\App\Http\Controllers\ComingSoonController::FEATURES) as $key) {
            $this->actingAs($user)->get("/soon/{$key}")->assertOk()->assertSee('What you will get');
        }
        $this->get('/soon/subscription')->assertSee('Razorpay');
        $this->get('/soon/nope')->assertNotFound();
    }

    public function test_switch_workspace_only_to_own(): void
    {
        $user = User::factory()->create();
        $first = $user->createWorkspace('First');
        $second = $user->createWorkspace('Second');
        $other = User::factory()->create()->createWorkspace('Other');

        $this->actingAs($user)->get('/dashboard')->assertSee('First');
        $this->post('/workspaces/switch', ['workspace_id' => $first->id])->assertRedirect('/dashboard');
        $this->assertSame($first->id, $user->fresh()->current_workspace_id);

        $this->post('/workspaces/switch', ['workspace_id' => $other->id])->assertForbidden();
        $this->assertSame($first->id, $user->fresh()->current_workspace_id);
    }
}
