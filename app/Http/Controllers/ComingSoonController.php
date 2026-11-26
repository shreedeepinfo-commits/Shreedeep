<?php

namespace App\Http\Controllers;

class ComingSoonController extends Controller
{
    public const FEATURES = [
        'drip' => ['Drip Campaign', 'Send a series of template messages over days, e.g. day 0 welcome, day 3 offer.', 'clock'],
        'bots' => ['Bot Responder', 'Auto-reply to keywords like "price", "hi" or "help".', 'bot'],
        'team' => ['User Management', 'Invite team members as admin or agent and assign chats.', 'team'],
        'profile' => ['My Profile', 'Business logo, profile details and password.', 'user'],
    ];

    public function __invoke(string $feature)
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        [$title, $description, $icon] = self::FEATURES[$feature];

        return view('coming-soon', compact('title', 'description', 'icon'));
    }
}
