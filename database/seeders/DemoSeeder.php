<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Flow;
use App\Models\Message;
use App\Models\QuickReply;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use App\Services\Flows\FlowStarters;
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

        MessageTemplate::withoutGlobalScopes()->firstOrCreate(['whatsapp_account_id' => $account->id, 'name' => 'flash_sale', 'language' => 'en'], [
            'workspace_id' => $ws, 'category' => 'MARKETING', 'status' => 'REJECTED', 'rejected_reason' => 'INVALID_FORMAT',
            'components' => [['type' => 'BODY', 'text' => '{{1}} FLASH SALE!!! Click now {{2}}']],
        ]);

        if (Contact::withoutGlobalScopes()->where('workspace_id', $ws)->exists()) {
            $this->extras($ws);

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
            $c->update(['last_message_at' => $m->created_at, 'last_inbound_at' => $m->created_at, 'chat_status' => 'open', 'unread_count' => $c->unread_count + 1]);
        }
        $this->extras($ws);
        $contacts[2]->update(['assigned_user_id' => User::where('email', 'agent@example.com')->value('id')]);
    }

    /** Parts that can be added to an existing demo database too (safe to re-run). */
    private function extras(int $ws): void
    {
        $agent = User::firstOrCreate(['email' => 'agent@example.com'], ['name' => 'Ravi Agent', 'password' => 'password']);
        Workspace::find($ws)->users()->syncWithoutDetaching([$agent->id => ['role' => 'agent']]);
        $agent->forceFill(['current_workspace_id' => $agent->current_workspace_id ?? $ws])->save();

        Flow::withoutGlobalScopes()->firstOrCreate(['workspace_id' => $ws, 'name' => 'Real estate enquiry'], [
            'triggers' => ['hi', 'hello', 'property'], 'status' => 'active',
            'definition' => FlowStarters::definition('real_estate'), 'triggered_count' => 42, 'completed_count' => 27,
        ]);
        Flow::withoutGlobalScopes()->firstOrCreate(['workspace_id' => $ws, 'name' => 'Shop menu'], [
            'triggers' => ['menu'], 'status' => 'draft', 'definition' => FlowStarters::definition('store'),
        ]);

        foreach ([
            'price' => "Our plans start at ₹999 per month. Shall I share the full price list?",
            'address' => "We are at Shree Deep Infotech, Main Road. Open 10 AM to 7 PM, Monday to Saturday.",
            'thanks' => "Thank you for contacting us! 🙏 Have a great day.",
        ] as $shortcut => $text) {
            QuickReply::withoutGlobalScopes()->firstOrCreate(['workspace_id' => $ws, 'shortcut' => $shortcut], ['body' => $text]);
        }

        // Keep the demo workspace usable: a fresh trial when it has no paid plan and the trial ran out.
        $workspace = Workspace::find($ws);
        if ($workspace->isExpired()) {
            $workspace->forceFill(['trial_ends_at' => now()->addDays(config('billing.trial_days'))])->save();
        }

        // Sample automations (only once). Scheduled ones start switched off so demo numbers get nothing.
        if (! Automation::withoutGlobalScopes()->where('workspace_id', $ws)->exists()) {
            $auto = fn (string $type, string $name, bool $active, array $config, int $sent = 0) => Automation::withoutGlobalScopes()->create(
                ['workspace_id' => $ws, 'type' => $type, 'name' => $name, 'active' => $active, 'config' => $config, 'sent_count' => $sent]);
            $auto('welcome', 'Welcome message', true, ['message' => "Hi {{name}}! 👋 Welcome to Shree Deep Infotech. Type *menu* to see what we offer."], 128);
            $auto('away', 'Away message', true, ['message' => "Thanks for your message! We are closed now and will reply when we open at 10 AM.", 'timezone' => Automation::DEFAULT_TIMEZONE, 'hours' => Automation::defaultHours()], 37);
            $auto('keyword', 'Price enquiry', true, ['keywords' => ['price', 'rate', 'cost'], 'match' => 'contains', 'reply' => "Our plans start at ₹999 per month. Reply *demo* to book a free demo."], 64);
            $auto('keyword', 'Location', true, ['keywords' => ['address', 'location'], 'match' => 'contains', 'reply' => "We are at Main Road. Open 10 AM to 7 PM, Monday to Saturday."], 19);
            $template = MessageTemplate::withoutGlobalScopes()->where('workspace_id', $ws)->where('status', 'APPROVED')->first();
            if ($template) {
                $step = ['template_id' => $template->id, 'variables' => ['body' => [['type' => 'name', 'fallback' => 'there']]], 'header_media_url' => null];
                $auto('drip', 'New lead nurture', false, ['audience' => ['groups' => [], 'tags' => ['hot lead']], 'enroll_new' => true, 'steps' => [
                    $step + ['delay_days' => 0, 'delay_hours' => 1], $step + ['delay_days' => 2, 'delay_hours' => 0], $step + ['delay_days' => 5, 'delay_hours' => 0],
                ]]);
                $auto('date', 'Birthday wishes', false, $step + ['field' => 'birthday', 'days_before' => 0, 'time' => '09:00', 'yearly' => true, 'timezone' => Automation::DEFAULT_TIMEZONE]);
            }
        }

        // Sample CRM data (only once).
        if (! Contact::withoutGlobalScopes()->where('workspace_id', $ws)->whereNotNull('deal_value')->exists()) {
            $leads = [
                ['interested', 250000, 1, 'Call about site visit', ['hot lead', 'pune']],
                ['negotiation', 480000, 0, 'Send final quote', ['hot lead']],
                ['contacted', 120000, 2, null, ['mumbai']],
                ['won', 350000, null, null, ['vip']],
                ['new', 90000, -1, 'Missed call, try again', []],
                ['lost', 60000, null, null, []],
                ['interested', 175000, 3, 'Share brochure', ['pune']],
                ['won', 210000, null, null, ['vip']],
            ];
            $contacts = Contact::withoutGlobalScopes()->where('workspace_id', $ws)->orderBy('id')->limit(count($leads))->get();
            foreach ($contacts as $i => $contact) {
                [$stage, $value, $days, $note, $tags] = $leads[$i];
                $contact->forceFill([
                    'lead_status' => $stage, 'deal_value' => $value, 'tags' => $tags ?: $contact->tags,
                    'follow_up_at' => $days === null ? null : now()->addDays($days)->setTime(11, 0),
                    'follow_up_note' => $days === null ? null : $note,
                ])->save();
            }
        }
    }
}
