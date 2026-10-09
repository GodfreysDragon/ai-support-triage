<?php

namespace App\Demo;

use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\User;

/**
 * The tickets a demo account starts with: a spread of categories, priorities
 * and moods, two saved reply drafts, and one failed triage so visitors can
 * try "Retry triage". Already triaged, so the dashboard isn't empty and no
 * AI call is needed to show it off.
 */
final class SampleTickets
{
    public static function seedFor(User $user): void
    {
        foreach (array_reverse(self::all()) as $attributes) {
            $minutesAgo = $attributes['minutes_ago'];
            unset($attributes['minutes_ago']);

            $ticket = $user->tickets()->make($attributes);
            $ticket->forceFill([
                'created_at' => now()->subMinutes($minutesAgo),
                'updated_at' => now()->subMinutes($minutesAgo),
                'triaged_at' => $ticket->status === TicketStatus::Triaged ? now()->subMinutes($minutesAgo - 1) : null,
            ])->save();
        }
    }

    /**
     * Newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'minutes_ago' => 4,
                'customer_email' => 'ops@northwind.test',
                'subject' => 'Checkout fails for every customer since the 2pm deploy',
                'body' => "Every checkout on our store has failed with \"payment could not be processed\" since about 2pm. We're losing sales by the minute and this is the second outage this month. We need someone on this right now.",
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::Bug,
                'priority' => TicketPriority::Urgent,
                'sentiment' => Sentiment::Angry,
                'summary' => 'All checkouts failing since a 2pm deploy; revenue impact and a repeat outage this month.',
                'tags' => ['checkout', 'payments', 'outage', 'deploy'],
                'draft_reply' => "I'm sorry checkout has been failing for your customers since 2pm, and that this is the second outage this month. We're treating it as our top priority and have escalated it to the payments team.\n\nTo speed things up, could you send:\n- One or two order IDs that failed\n- The exact error your customers see\n\nWe'll update you within the hour, and the team will follow up on the repeat outage once checkout is working again.\n\nThe Support Team",
            ],
            [
                'minutes_ago' => 38,
                'customer_email' => 'finance@globex.test',
                'subject' => 'Charged twice for September',
                'body' => 'We were billed twice for our September invoice (INV-2291). Can you refund the duplicate charge? This is the second time this has happened, which is pretty frustrating.',
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::Billing,
                'priority' => TicketPriority::High,
                'sentiment' => Sentiment::Negative,
                'summary' => 'Double-charged for invoice INV-2291 and wants the duplicate refunded; second occurrence.',
                'tags' => ['duplicate-charge', 'refund', 'invoice'],
                'draft_reply' => null,
            ],
            [
                'minutes_ago' => 95,
                'customer_email' => 'it@initech.test',
                'subject' => 'SSO login keeps looping back to the sign-in page',
                'body' => 'Since this morning, signing in with Okta sends our staff back to the sign-in page instead of the dashboard. Password login still works. About 40 people are affected.',
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::Account,
                'priority' => TicketPriority::High,
                'sentiment' => Sentiment::Neutral,
                'summary' => 'Okta SSO redirects back to sign-in for ~40 staff; password login unaffected.',
                'tags' => ['sso', 'okta', 'login'],
                'draft_reply' => null,
            ],
            [
                'minutes_ago' => 160,
                'customer_email' => 'dana@umbrella.test',
                'subject' => 'Mobile app crashes when attaching photos',
                'body' => "The iOS app closes as soon as I pick a photo to attach to a report. It started after yesterday's update. iPhone 15, iOS 19.",
                'status' => TicketStatus::Failed,
                'error' => 'The AI service timed out. Please retry.',
                'draft_reply' => null,
            ],
            [
                'minutes_ago' => 240,
                'customer_email' => 'sam@hooli.test',
                'subject' => 'Can we schedule recurring exports?',
                'body' => 'Love the product! Is there a way to schedule the CSV export to run every Monday and email it to my team? If not, that would be a great feature.',
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::FeatureRequest,
                'priority' => TicketPriority::Low,
                'sentiment' => Sentiment::Positive,
                'summary' => 'Asks for scheduled weekly CSV exports emailed to the team.',
                'tags' => ['export', 'csv', 'scheduling'],
                'draft_reply' => "Thanks so much for the kind words! You can't schedule exports yet, but I've passed your request for weekly emailed CSV exports to our product team.\n\nIn the meantime, you can run the export manually from Reports > Export at any time.\n\nThe Support Team",
            ],
            [
                'minutes_ago' => 410,
                'customer_email' => 'lee@stark.test',
                'subject' => 'How do I make a teammate an admin?',
                'body' => "I've invited a colleague but can't find where to give her admin rights. Where is that setting?",
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::HowTo,
                'priority' => TicketPriority::Low,
                'sentiment' => Sentiment::Neutral,
                'summary' => 'Wants to know where to grant admin rights to an invited teammate.',
                'tags' => ['permissions', 'admin', 'team'],
                'draft_reply' => null,
            ],
            [
                'minutes_ago' => 700,
                'customer_email' => 'accounts@wayne.test',
                'subject' => 'VAT invoice needed for our records',
                'body' => 'Our accountant needs invoices showing our VAT number for the last quarter. Could you reissue them?',
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::Billing,
                'priority' => TicketPriority::Medium,
                'sentiment' => Sentiment::Neutral,
                'summary' => 'Needs last quarter’s invoices reissued with their VAT number.',
                'tags' => ['invoice', 'vat', 'tax'],
                'draft_reply' => null,
            ],
            [
                'minutes_ago' => 1300,
                'customer_email' => 'kim@acme.test',
                'subject' => 'The new dashboard is great',
                'body' => 'Just wanted to say the redesigned dashboard is so much faster. Our team loves it. Thanks!',
                'status' => TicketStatus::Triaged,
                'category' => TicketCategory::Other,
                'priority' => TicketPriority::Low,
                'sentiment' => Sentiment::Positive,
                'summary' => 'Positive feedback on the faster redesigned dashboard.',
                'tags' => ['feedback', 'dashboard'],
                'draft_reply' => null,
            ],
        ];
    }
}
