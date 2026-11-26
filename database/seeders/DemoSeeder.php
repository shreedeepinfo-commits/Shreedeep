<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Database\Seeder;

/**
 * Local demo data: log in as demo@example.com / password.
 * The WhatsApp number and token are fake, so real sends will fail.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'demo@example.com'], ['name' => 'Demo Owner', 'password' => 'password']);
        $ws = $user->current_workspace_id ?? $user->createWorkspace('Demo Traders')->id;

        $account = WhatsappAccount::withoutGlobalScopes()->firstOrCreate(['phone_number_id' => '100000000000001'], [
            'workspace_id' => $ws, 'waba_id' => '200000000000001', 'display_phone_number' => '+91 98765 00000',
            'verified_name' => 'Demo Traders', 'quality_rating' => 'GREEN', 'messaging_limit' => 'TIER_1K',
            'access_token' => 'demo-token-not-real', 'connected_via' => 'manual',
        ]);

        $offer = MessageTemplate::withoutGlobalScopes()->firstOrCreate(['whatsapp_account_id' => $account->id, 'name' => 'diwali_offer', 'language' => 'hi'], [
            'workspace_id' => $ws, 'category' => 'MARKETING', 'status' => 'APPROVED',
            'components' => [
                ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Diwali Sale'],
                ['type' => 'BODY', 'text' => "नमस्ते {{1}}, दिवाली सेल शुरू! सभी प्रोडक्ट्स पर {{2}} छूट। आज ही {{3}} स्टोर पर आएं।"],
                ['type' => 'FOOTER', 'text' => 'Reply STOP to unsubscribe'],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Interested']]],
            ],
        ]);
        MessageTemplate::withoutGlobalScopes()->firstOrCreate(['whatsapp_account_id' => $account->id, 'name' => 'hello_world', 'language' => 'en_US'], [
            'workspace_id' => $ws, 'category' => 'UTILITY', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hello World']],
        ]);
        MessageTemplate::withoutGlobalScopes()->firstOrCreate(['whatsapp_account_id' => $account->id, 'name' => 'order_update', 'language' => 'en'], [
            'workspace_id' => $ws, 'category' => 'UTILITY', 'status' => 'PENDING',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, your order {{2}} has been shipped.']],
        ]);

        if (Contact::withoutGlobalScopes()->where('workspace_id', $ws)->exists()) {
            return;
        }

        $vip = ContactGroup::withoutGlobalScopes()->create(['workspace_id' => $ws, 'name' => 'VIP customers']);
        $all = ContactGroup::withoutGlobalScopes()->create(['workspace_id' => $ws, 'name' => 'Pune leads']);
        $names = ['Ravi Sharma', 'Sita Patel', 'Amit Verma', 'Pooja Singh', 'Rahul Gupta', 'Neha Joshi', 'Vikas Yadav', 'Anjali Mehta',
            'Suresh Kumar', 'Kavita Rao', 'Manoj Tiwari', 'Priya Nair', 'Deepak Jain', 'Sunita Das', 'Arjun Reddy'];
        $contacts = collect($names)->map(fn ($name, $i) => Contact::withoutGlobalScopes()->create([
            'workspace_id' => $ws, 'phone' => '9198765'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), 'name' => $name,
            'fields' => ['city' => ['Pune', 'Mumbai', 'Delhi'][$i % 3]], 'opted_in_at' => now()->subDays(30),
            'opt_in_source' => 'website form', 'opted_out_at' => $i === 14 ? now()->subDays(2) : null,
        ]));
        $vip->contacts()->attach($contacts->take(6)->pluck('id'));
        $all->contacts()->attach($contacts->pluck('id'));

        $campaign = Campaign::withoutGlobalScopes()->create([
            'workspace_id' => $ws, 'whatsapp_account_id' => $account->id, 'message_template_id' => $offer->id,
            'name' => 'Diwali offer 2026', 'status' => 'completed', 'audience' => ['all' => false, 'groups' => [$all->id]],
            'variables' => ['body' => [['type' => 'name'], ['type' => 'text', 'value' => '30%'], ['type' => 'field', 'value' => 'city']]],
            'total' => 14, 'started_at' => now()->subDay(), 'completed_at' => now()->subDay()->addMinutes(2),
        ]);
        $statuses = ['read', 'read', 'read', 'read', 'read', 'delivered', 'delivered', 'delivered', 'delivered', 'sent', 'sent', 'failed', 'read', 'delivered'];
        foreach ($contacts->take(14) as $i => $c) {
            $s = $statuses[$i];
            $sent = now()->subDay()->addSeconds($i);
            Message::withoutGlobalScopes()->create([
                'workspace_id' => $ws, 'whatsapp_account_id' => $account->id, 'campaign_id' => $campaign->id, 'contact_id' => $c->id,
                'direction' => 'out', 'wamid' => 'wamid.demo.'.$i, 'phone' => $c->phone, 'type' => 'template', 'status' => $s,
                'body' => $offer->render([explode(' ', $c->name)[0], '30%', $c->fields['city']]),
                'sent_at' => $s === 'failed' ? null : $sent,
                'delivered_at' => in_array($s, ['delivered', 'read']) ? $sent->copy()->addSeconds(5) : null,
                'read_at' => $s === 'read' ? $sent->copy()->addMinutes(10) : null,
                'failed_at' => $s === 'failed' ? $sent : null,
                'error_code' => $s === 'failed' ? '131026' : null,
                'error_message' => $s === 'failed' ? 'Message undeliverable: number not on WhatsApp' : null,
            ]);
        }

        foreach ([[0, 'Interested'], [0, 'Kya offer Pune store par bhi hai?'], [2, 'Price list bhejo please'], [4, 'Interested']] as $k => [$idx, $text]) {
            $c = $contacts[$idx];
            $m = new Message([
                'workspace_id' => $ws, 'whatsapp_account_id' => $account->id, 'contact_id' => $c->id, 'direction' => 'in',
                'wamid' => 'wamid.demo.in.'.$k, 'phone' => $c->phone, 'type' => 'text', 'body' => $text, 'status' => 'received',
            ]);
            $m->created_at = now()->subMinutes(60 - $k * 10);
            $m->save();
            $c->update(['last_message_at' => $m->created_at]);
        }
    }
}
