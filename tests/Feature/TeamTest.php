<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Message;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $ws;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.app_secret' => 'shh']);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
        $this->owner = User::factory()->create(['name' => 'Dilip Owner']);
        $this->ws = $this->owner->createWorkspace('Shop');
        $this->account = WhatsappAccount::create([
            'workspace_id' => $this->ws->id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'status' => 'connected',
        ]);
    }

    private function member(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->ws->users()->attach($user->id, ['role' => $role]);
        $user->forceFill(['current_workspace_id' => $this->ws->id])->save();

        return $user;
    }

    private function inbound(string $from, string $text = 'Hi')
    {
        $value = [
            'messaging_product' => 'whatsapp', 'metadata' => ['phone_number_id' => '555'],
            'contacts' => [['profile' => ['name' => 'C'.$from], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => 'wamid.IN'.uniqid(), 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => $text]]],
        ];
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh'),
        ], $body)->assertOk();
    }

    public function test_agents_only_reach_chat_pages(): void
    {
        $agent = $this->member('agent', 'Ravi Agent');
        $this->actingAs($agent);

        foreach (['/campaigns', '/campaigns/create', '/templates', '/whatsapp', '/flows', '/team'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/quick-replies', ['shortcut' => 'x', 'body' => 'y'])->assertForbidden();
        foreach (['/dashboard', '/inbox', '/contacts', '/quick-replies', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertDontSee('Chatbot Builder')->assertSee('Live Chat');

        $this->actingAs($this->owner)->get('/team')->assertOk()->assertSee('Ravi Agent');
    }

    public function test_agent_sees_only_own_and_unassigned_chats(): void
    {
        $agent = $this->member('agent', 'Ravi Agent');
        $other = $this->member('agent', 'Sita Agent');
        $this->inbound('919800000001');
        $this->inbound('919800000002');
        $this->inbound('919800000003');
        $mine = Contact::withoutGlobalScopes()->where('phone', '919800000001')->first();
        $theirs = Contact::withoutGlobalScopes()->where('phone', '919800000002')->first();
        $mine->update(['assigned_user_id' => $agent->id]);
        $theirs->update(['assigned_user_id' => $other->id]);

        $this->actingAs($agent);
        $this->get('/inbox')->assertSee('C919800000001')->assertSee('C919800000003')->assertDontSee('C919800000002');
        $this->get("/inbox/{$theirs->id}")->assertForbidden();
        $this->postJson("/inbox/{$theirs->id}", ['body' => 'x'])->assertForbidden();
        $this->getJson("/inbox/poll?contact={$theirs->id}")->assertOk()->assertJsonPath('counts.all', 2)->assertJsonCount(0, 'messages');

        // Transferring a chat to a colleague takes it out of the agent's view.
        $this->post("/inbox/{$mine->id}/assign", ['user_id' => $other->id])->assertRedirect('/inbox');
        $this->get("/inbox/{$mine->id}")->assertForbidden();

        $this->actingAs($this->owner)->get("/inbox/{$theirs->id}")->assertOk();
    }

    public function test_invite_link_creates_account_and_joins_team(): void
    {
        $this->actingAs($this->owner)->post('/team/invitations', ['name' => 'Asha', 'email' => 'Asha@Example.com', 'role' => 'agent'])->assertRedirect();
        $invite = TeamInvitation::firstOrFail();
        $this->assertSame('asha@example.com', $invite->email);
        $this->get('/team')->assertSee($invite->url());

        $this->post('/team/invitations', ['name' => 'Asha', 'email' => 'asha@example.com', 'role' => 'agent'])->assertSessionHasErrors('email');
        $this->post('/team/invitations', ['name' => 'Me', 'email' => $this->owner->email, 'role' => 'agent'])->assertSessionHasErrors('email');
        $this->post('/team/invitations', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'owner'])->assertSessionHasErrors('role');

        auth()->logout();
        $this->get("/invite/{$invite->token}")->assertOk()->assertSee('Join Shop');
        $this->post("/invite/{$invite->token}", ['name' => 'Asha K', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertRedirect('/inbox');

        $asha = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($asha);
        $this->assertSame('agent', $this->ws->users()->whereKey($asha->id)->first()->pivot->role);
        $this->assertSame($this->ws->id, $asha->current_workspace_id);
        $this->assertModelMissing($invite);
        $this->get("/invite/{$invite->token}")->assertNotFound();
    }

    public function test_existing_user_must_log_in_to_accept_and_expired_links_fail(): void
    {
        $existing = User::factory()->create(['email' => 'old@example.com']);
        $invite = TeamInvitation::withoutGlobalScopes()->create([
            'workspace_id' => $this->ws->id, 'email' => 'old@example.com', 'name' => 'Old', 'role' => 'admin', 'token' => 'tok123', 'expires_at' => now()->addDay(),
        ]);

        $this->post('/invite/tok123')->assertRedirect('/login');
        $this->actingAs($this->owner)->post('/invite/tok123')->assertRedirect('/login');
        $this->assertFalse($existing->belongsToWorkspace($this->ws));

        $this->actingAs($existing)->post('/invite/tok123')->assertRedirect('/inbox');
        $this->assertTrue($existing->fresh()->belongsToWorkspace($this->ws));

        TeamInvitation::withoutGlobalScopes()->create([
            'workspace_id' => $this->ws->id, 'email' => 'late@example.com', 'role' => 'agent', 'token' => 'old456', 'expires_at' => now()->subMinute(),
        ]);
        $this->get('/invite/old456')->assertNotFound();
    }

    public function test_roles_and_removal_rules(): void
    {
        $admin = $this->member('admin', 'Admin A');
        $admin2 = $this->member('admin', 'Admin B');
        $agent = $this->member('agent', 'Ravi Agent');
        $this->inbound('919800000009');
        Contact::withoutGlobalScopes()->update(['assigned_user_id' => $agent->id]);

        $this->actingAs($admin);
        $this->put("/team/members/{$this->owner->id}", ['role' => 'agent'])->assertForbidden();
        $this->delete("/team/members/{$this->owner->id}")->assertForbidden();
        $this->delete("/team/members/{$admin2->id}")->assertForbidden(); // only the owner removes admins
        $this->delete("/team/members/{$admin->id}")->assertForbidden(); // not yourself

        $this->put("/team/members/{$agent->id}", ['role' => 'admin'])->assertRedirect();
        $this->assertSame('admin', $this->ws->users()->whereKey($agent->id)->first()->pivot->role);
        $this->put("/team/members/{$agent->id}", ['role' => 'agent'])->assertRedirect();

        $this->delete("/team/members/{$agent->id}")->assertRedirect();
        $this->assertFalse($agent->fresh()->belongsToWorkspace($this->ws));
        $this->assertNull(Contact::withoutGlobalScopes()->first()->assigned_user_id);

        $outsider = User::factory()->create();
        $this->put("/team/members/{$outsider->id}", ['role' => 'agent'])->assertNotFound();
    }

    public function test_round_robin_assigns_new_chats_to_agents_in_turn(): void
    {
        $a = $this->member('agent', 'Agent A');
        $b = $this->member('agent', 'Agent B');
        $this->actingAs($this->owner)->put('/team/settings', ['auto_assign' => '1'])->assertRedirect();
        $this->assertTrue($this->ws->fresh()->auto_assign);

        foreach (['919800000011', '919800000012', '919800000013'] as $phone) {
            $this->inbound($phone);
        }
        $this->inbound('919800000011', 'again'); // already assigned: stays with the same agent

        $assigned = Contact::withoutGlobalScopes()->orderBy('phone')->pluck('assigned_user_id', 'phone');
        $this->assertSame([$a->id, $b->id, $a->id], [$assigned['919800000011'], $assigned['919800000012'], $assigned['919800000013']]);
        $this->assertSame(3, ContactNote::withoutGlobalScopes()->where('system', true)->count());

        $this->put('/team/settings', ['auto_assign' => '0']);
        $this->inbound('919800000014');
        $this->assertNull(Contact::withoutGlobalScopes()->where('phone', '919800000014')->first()->assigned_user_id);
    }

    public function test_agent_report_counts_replies_and_first_response_time(): void
    {
        $agent = $this->member('agent', 'Ravi Agent');
        $this->inbound('919800000021');
        $contact = Contact::withoutGlobalScopes()->first();
        Message::withoutGlobalScopes()->update(['created_at' => now()->subMinutes(4)]);

        $this->actingAs($agent)->postJson("/inbox/{$contact->id}", ['body' => 'Hello'])->assertOk();
        $this->postJson("/inbox/{$contact->id}", ['body' => 'More info'])->assertOk(); // not a first response
        $this->post("/inbox/{$contact->id}/status", ['status' => 'resolved']);

        $this->actingAs($this->owner)->get('/team')->assertOk()->assertSeeInOrder(['Ravi Agent', '0', '2', '1', '4m']);
    }

    public function test_profile_update_and_password_change(): void
    {
        $this->actingAs($this->owner);
        $this->put('/profile', ['name' => 'Dilip Kumar', 'email' => 'dilip@example.com'])->assertRedirect();
        $this->assertSame('Dilip Kumar', $this->owner->fresh()->name);

        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-secret-1', $this->owner->fresh()->password));
    }
}
