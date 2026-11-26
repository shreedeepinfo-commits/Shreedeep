<?php

namespace App\Services\Flows;

/** Ready-made flows customers can start from. */
class FlowStarters
{
    public static function all(): array
    {
        return [
            'real_estate' => ['Real estate enquiry', 'Sales or support, city, property type, budget.', ['hi', 'hello', 'property']],
            'store' => ['Shop / store', 'Offers, store timings, talk to an agent.', ['hi', 'menu']],
            'blank' => ['Start from scratch', 'One welcome step with buttons.', ['hi']],
        ];
    }

    public static function definition(string $key): array
    {
        return match ($key) {
            'real_estate' => [
                'start' => 'welcome',
                'nodes' => [
                    'welcome' => ['type' => 'buttons', 'text' => "Namaste! 🙏\nWelcome to our real estate team. How can we help you today?", 'options' => [
                        ['title' => 'Sales', 'next' => 'city'],
                        ['title' => 'Support', 'next' => 'support'],
                    ]],
                    'city' => ['type' => 'buttons', 'text' => 'Great! Which city are you looking in?', 'options' => [
                        ['title' => 'Mumbai', 'next' => 'property'],
                        ['title' => 'Pune', 'next' => 'property'],
                    ]],
                    'property' => ['type' => 'list', 'text' => 'What kind of property do you want?', 'button' => 'Choose property', 'options' => [
                        ['title' => 'Plot', 'description' => 'Residential and NA plots', 'next' => 'budget'],
                        ['title' => 'Villa', 'description' => 'Independent villas', 'next' => 'budget'],
                        ['title' => 'Flat 2/3 BHK', 'description' => 'Apartments', 'next' => 'budget'],
                        ['title' => 'Commercial', 'description' => 'Shops and offices', 'next' => 'budget'],
                    ]],
                    'budget' => ['type' => 'question', 'text' => 'What is your budget? (for example: 50 lakh)', 'save_as' => 'budget', 'next' => 'thanks'],
                    'thanks' => ['type' => 'end', 'text' => "Thank you! 🏡 Our sales executive will call you within 30 minutes with the best options."],
                    'support' => ['type' => 'question', 'text' => 'Please describe your issue in one message.', 'save_as' => 'issue', 'next' => 'support_end'],
                    'support_end' => ['type' => 'handoff', 'text' => 'Thanks! A support executive will reply to you here shortly.'],
                ],
            ],
            'store' => [
                'start' => 'welcome',
                'nodes' => [
                    'welcome' => ['type' => 'buttons', 'text' => 'Hello! 👋 What would you like to do?', 'options' => [
                        ['title' => "Today's offers", 'next' => 'offers'],
                        ['title' => 'Store timings', 'next' => 'timings'],
                        ['title' => 'Talk to us', 'next' => 'agent'],
                    ]],
                    'offers' => ['type' => 'message', 'text' => '🔥 Flat 20% off on all products this week!', 'next' => 'more'],
                    'more' => ['type' => 'buttons', 'text' => 'Anything else?', 'options' => [
                        ['title' => 'Store timings', 'next' => 'timings'],
                        ['title' => 'Talk to us', 'next' => 'agent'],
                    ]],
                    'timings' => ['type' => 'end', 'text' => 'We are open every day, 10 AM to 9 PM.'],
                    'agent' => ['type' => 'handoff', 'text' => 'Sure! Our team will reply here in a few minutes.'],
                ],
            ],
            default => [
                'start' => 'welcome',
                'nodes' => [
                    'welcome' => ['type' => 'buttons', 'text' => 'Hello! How can we help you?', 'options' => [
                        ['title' => 'Option 1', 'next' => null],
                        ['title' => 'Option 2', 'next' => null],
                    ]],
                ],
            ],
        };
    }
}
