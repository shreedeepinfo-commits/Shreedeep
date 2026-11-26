<?php

namespace App\Http\Controllers;

/** Sidebar sections that are on the roadmap but not built yet: each shows what is coming. */
class ComingSoonController extends Controller
{
    /** key => [title, description, icon, roadmap phase, planned features] */
    public const FEATURES = [
        'whatsapp-flows' => ['WhatsApp Flows', 'Meta WhatsApp Flows: forms, bookings and lead capture inside WhatsApp.', 'form', 'Later', [
            'Create and publish Meta Flows', 'Lead forms and appointment booking', 'Send Flows in templates and chats', 'See submitted responses',
        ]],
        'integrations' => ['Integrations', 'Connect your store, CRM and website with WhatsApp.', 'plug', 'Phase 10', [
            'Shopify and WooCommerce', 'Google Sheets', 'Zoho CRM', 'WordPress', 'Zapier and Make',
            'Website WhatsApp chat widget', 'WhatsApp QR code and link generator', 'Click-to-WhatsApp Ads tracking',
        ]],
        'api' => ['API & Webhooks', 'Send messages from your own software.', 'code', 'Phase 10', [
            'API key generation and management', 'Send message and template APIs', 'Incoming message and delivery webhooks',
            'API documentation', 'Request logs',
        ]],
        'analytics' => ['Analytics', 'Deep reports on messages, campaigns, chats and agents.', 'chart', 'Phase 10', [
            'Date-wise message reports', 'Campaign performance', 'Messaging consumption by category',
            'Agent performance', 'Downloadable reports',
        ]],
        'subscription' => ['Subscription', 'Your plan, renewals and invoices.', 'card', 'Phase 7', [
            'Monthly, quarterly and annual plans', 'Free trial', 'Plan upgrade and downgrade', 'Extra agents and numbers',
            'Pay with Razorpay or Cashfree', 'GST invoices', 'Renewal and expiry reminders',
        ]],
        'support' => ['Help & Support', 'Guides, FAQs and support tickets.', 'help', 'Phase 10', [
            'Raise and track support tickets', 'Step-by-step setup guides', 'FAQs', 'Contact support',
        ]],
    ];

    public function __invoke(string $feature)
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        [$title, $description, $icon, $phase, $features] = self::FEATURES[$feature];

        return view('coming-soon', compact('title', 'description', 'icon', 'phase', 'features'));
    }
}
