<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->ws = $this->owner->createWorkspace('Sharma Electronics');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_super_admin' => true])->save();
    }

    private function raise(array $overrides = []): SupportTicket
    {
        $this->actingAs($this->owner)->post('/support', $overrides + [
            'subject' => 'Template rejected', 'category' => 'whatsapp', 'priority' => 'high', 'body' => 'My template got rejected, please help.',
        ])->assertRedirect();

        return SupportTicket::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_customer_raises_ticket_and_admin_replies(): void
    {
        $this->actingAs($this->owner)->get('/support')->assertOk();
        $this->get('/support?tab=faq')->assertOk();
        $this->get('/support?tab=guides')->assertOk();

        $ticket = $this->raise();
        $this->assertSame($this->ws->id, $ticket->workspace_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->replies()->count());
        $this->get("/support/{$ticket->id}")->assertOk()->assertSee('Template rejected')->assertSee('please help');

        $this->actingAs($this->admin)->get('/admin/tickets')->assertOk()->assertSee('Template rejected')->assertSee('Sharma Electronics');
        $this->get("/admin/tickets/{$ticket->id}")->assertOk()->assertSee('please help');
        $this->post("/admin/tickets/{$ticket->id}/reply", ['body' => 'Fixed it, please resubmit.'])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('answered', $ticket->status);
        $this->assertTrue((bool) $ticket->replies()->reorder()->latest("id")->first()->from_support);

        // Customer sees the reply and answering reopens the ticket
        $this->actingAs($this->owner)->get("/support/{$ticket->id}")->assertSee('Fixed it, please resubmit.');
        $this->post("/support/{$ticket->id}/reply", ['body' => 'Still rejected'])->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);

        $this->post("/support/{$ticket->id}/close")->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->actingAs($this->admin)->get('/admin/tickets')->assertDontSee('Template rejected');
        $this->get('/admin/tickets?status=closed')->assertSee('Template rejected');
    }

    public function test_admin_can_reply_and_close(): void
    {
        $ticket = $this->raise();
        $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/reply", ['body' => 'Done', 'close' => '1']);
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->post("/admin/tickets/{$ticket->id}/status", ['status' => 'open']);
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_other_workspaces_cannot_see_ticket(): void
    {
        $ticket = $this->raise();
        $other = User::factory()->create();
        $other->createWorkspace('Patel Clinic');

        $this->actingAs($other)->get('/support')->assertOk()->assertDontSee('Template rejected');
        $this->get("/support/{$ticket->id}")->assertNotFound();
        $this->post("/support/{$ticket->id}/reply", ['body' => 'hack'])->assertNotFound();
        $this->assertSame(1, $ticket->replies()->count());
    }

    public function test_only_super_admin_opens_ticket_inbox(): void
    {
        $ticket = $this->raise();
        $this->actingAs($this->owner)->get('/admin/tickets')->assertNotFound();
        $this->post("/admin/tickets/{$ticket->id}/reply", ['body' => 'x'])->assertNotFound();
    }

    public function test_agents_and_expired_workspaces_can_use_support(): void
    {
        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/support')->assertOk();

        $this->ws->update(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($this->owner->fresh())->get('/support')->assertOk();
        $ticket = $this->raise(['subject' => 'Renewal question', 'category' => 'billing']);
        $this->assertSame('Renewal question', $ticket->subject);
    }

    public function test_validation_and_open_ticket_limit(): void
    {
        $this->actingAs($this->owner)->post('/support', ['subject' => '', 'category' => 'nope', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'category', 'body']);

        for ($i = 0; $i < 10; $i++) {
            SupportTicket::withoutGlobalScopes()->create(['workspace_id' => $this->ws->id, 'user_id' => $this->owner->id, 'subject' => "T$i", 'category' => 'other', 'priority' => 'normal', 'status' => 'open', 'last_reply_at' => now()]);
        }
        $this->post('/support', ['subject' => 'One more', 'category' => 'other', 'body' => 'hi'])->assertSessionHasErrors('subject');
        $this->assertSame(10, SupportTicket::withoutGlobalScopes()->count());
    }
}
