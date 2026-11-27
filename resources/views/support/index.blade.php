@extends('layouts.app', ['title' => 'Help & Support'])
@php
    use App\Models\SupportTicket;
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $tabs = ['tickets' => ['My tickets', 'chat'], 'faq' => ['FAQs', 'help'], 'guides' => ['Setup guides', 'document']];
    $faqs = [
        'WhatsApp & Meta' => [
            ['How do I connect my WhatsApp number?', 'Go to WhatsApp Settings and click "Connect with Meta". Log in to Facebook, choose or create your Business account, add your number and verify it with the OTP. It takes about 2 minutes.'],
            ['Can I use the same number on the WhatsApp Business app?', 'Yes. Choose "keep using the WhatsApp Business app" while connecting (coexistence). Your chats on the phone app continue and the panel gets API access too.'],
            ['Who pays for WhatsApp messages?', 'Meta charges your own Meta account directly for template messages (marketing, utility, authentication). Replies inside the 24-hour customer window are free. Our plan is only for using the panel.'],
            ['What is the 24-hour window?', 'When a customer messages you, you can reply with normal messages for 24 hours. After that, WhatsApp allows only approved templates until the customer writes again. Live Chat shows a timer for this.'],
            ['Why did my message fail?', 'Common reasons: the number is not on WhatsApp, the customer blocked business messages, Meta limited marketing messages to that person, or the template is paused. Open the campaign report or Analytics to see the exact reason.'],
        ],
        'Campaigns & templates' => [
            ['Why was my template rejected?', 'Meta rejects templates with wrong variable formats, misleading content, or a category that does not match. Open Templates → Rejected to see Meta\'s reason, fix it and send again.'],
            ['How many messages can I send per day?', 'Meta sets a daily limit for each number (250, 1K, 10K, 100K or unlimited unique customers). It grows automatically when you send good quality messages. See it in WhatsApp Settings.'],
            ['Can I upload contacts from Excel?', 'Yes. Contacts → Import accepts .xlsx and .csv. You can also paste numbers directly when creating a campaign.'],
        ],
        'Plan & billing' => [
            ['How does the free trial work?', 'Every new account gets a free trial with Growth plan features. Choose a plan any time from Subscription; your trial days are not lost when you switch.'],
            ['Will I get a GST invoice?', 'Yes. Every payment creates a GST invoice. Add your GSTIN in Subscription → Billing details to claim input tax credit.'],
            ['What happens when my plan ends?', 'You can still see everything, but sending and changes are paused until you renew. Nothing is deleted.'],
        ],
    ];
    $guides = [
        ['Connect your WhatsApp number', 'whatsapp', ['Open WhatsApp Settings.', 'Click "Connect with Meta" and log in to Facebook.', 'Choose your Business portfolio and WhatsApp Business account.', 'Add your display name and phone number, then enter the OTP.', 'Done: the number shows as Connected.'], 'whatsapp.index'],
        ['Send your first campaign', 'megaphone', ['Create a template in Templates and wait for Meta approval (minutes to a few hours).', 'Import contacts or keep a list of numbers ready.', 'Campaigns → New campaign: pick the template and audience.', 'Map the variables ({{1}}, {{2}}) to name or other columns.', 'Send now or schedule. Watch delivery and read rates live.'], 'campaigns.create'],
        ['Set up a chatbot', 'bot', ['Open Chatbot Builder and pick a ready-made flow.', 'Edit the messages, buttons and list options.', 'Set trigger words like "hi" or "menu".', 'Click "Go live" and test it from your phone.'], 'flows.index'],
        ['Add your team', 'team', ['Team Management → Invite member.', 'Choose Agent (only chats) or Admin (everything except billing).', 'They open the invite link and set a password.', 'Turn on auto-assign to share new chats one by one.'], 'team.index'],
    ];
@endphp

@section('content')
<x-page-header title="Help & Support" subtitle="Answers, step-by-step guides and a direct line to our team." />

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col lg:flex-row lg:items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-white/10 grid place-items-center"><x-icon name="help" class="w-6 h-6" /></span>
        <div class="flex-1">
            <div class="font-semibold text-lg">We are here to help</div>
            <div class="text-brand-100 text-sm">Raise a ticket and our team replies here, usually within a few hours (Mon–Sat, 10 AM – 7 PM).</div>
        </div>
        <a href="#new-ticket" class="inline-flex items-center gap-2 rounded-xl bg-white text-brand-700 px-4 py-2.5 text-sm font-semibold"><x-icon name="plus" class="w-4 h-4" /> New ticket</a>
    </div>
</section>

<div class="flex gap-2 mb-5">
    @foreach ($tabs as $k => [$label, $icon])
        <a href="{{ route('support.index', ['tab' => $k]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold {{ $tab === $k ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:text-brand-700' }}"><x-icon :name="$icon" class="w-4 h-4" />{{ $label }}</a>
    @endforeach
</div>

@if ($tab === 'tickets')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 {{ $card }} overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-semibold">Your tickets</div>
            @forelse ($tickets as $t)
                @php [$sl, $sc] = SupportTicket::STATUSES[$t->status]; @endphp
                <a href="{{ route('support.show', $t) }}" class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-slate-50 hover:bg-slate-50">
                    <span class="text-xs text-slate-400 w-14">{{ $t->number() }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-medium truncate">{{ $t->subject }} @if ($t->priority === 'high')<span class="text-xs text-red-600">· urgent</span>@endif</span>
                        <span class="block text-xs text-slate-500">{{ SupportTicket::CATEGORIES[$t->category] ?? $t->category }} · by {{ $t->user?->name }} · {{ $t->last_reply_at?->diffForHumans() }}</span>
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $sc }}">{{ $sl }}</span>
                </a>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No tickets yet. Check the FAQs, or ask us anything using the form.</div>
            @endforelse
        </div>
        <form id="new-ticket" method="POST" action="{{ route('support.store') }}" class="{{ $card }} p-6 space-y-3 h-fit">
            @csrf
            <div class="font-semibold">New ticket</div>
            <input name="subject" value="{{ old('subject') }}" required maxlength="150" placeholder="Short summary of the problem" class="{{ $input }}">
            <div class="grid grid-cols-2 gap-2">
                <select name="category" class="{{ $input }}">@foreach (SupportTicket::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select>
                <select name="priority" class="{{ $input }}"><option value="normal">Normal</option><option value="high" @selected(old('priority') === 'high')>Urgent</option></select>
            </div>
            <textarea name="body" rows="6" required maxlength="5000" placeholder="Tell us what happened. Campaign name, phone number or a screenshot link helps us fix it faster." class="{{ $input }}">{{ old('body') }}</textarea>
            <x-btn icon="send">Send ticket</x-btn>
        </form>
    </div>
@endif

@if ($tab === 'faq')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        @foreach ($faqs as $section => $items)
            <div class="{{ $card }} p-6">
                <div class="font-semibold mb-3">{{ $section }}</div>
                @foreach ($items as [$q, $a])
                    <details class="group border-b border-slate-100 py-3">
                        <summary class="cursor-pointer text-sm font-medium flex justify-between gap-2 list-none">{{ $q }}<x-icon name="chevron-down" class="w-4 h-4 shrink-0 text-slate-400 group-open:rotate-180 transition" /></summary>
                        <p class="text-sm text-slate-600 mt-2">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        @endforeach
    </div>
    <p class="text-sm text-slate-500 mt-6">Still stuck? <a href="{{ route('support.index') }}#new-ticket" class="text-brand-700 font-semibold">Raise a ticket</a>.</p>
@endif

@if ($tab === 'guides')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($guides as [$title, $icon, $steps, $route])
            <div class="{{ $card }} p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon :name="$icon" class="w-5 h-5" /></span>
                    <div class="font-semibold flex-1">{{ $title }}</div>
                    @if ($isManager ?? false)<a href="{{ route($route) }}" class="text-sm font-semibold text-brand-700">Open →</a>@endif
                </div>
                <ol class="space-y-3">
                    @foreach ($steps as $i => $s)
                        <li class="flex gap-3 text-sm"><span class="w-6 h-6 rounded-full bg-brand-600 text-white text-xs font-bold grid place-items-center shrink-0">{{ $i + 1 }}</span><span class="pt-0.5">{{ $s }}</span></li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
@endif
@endsection
