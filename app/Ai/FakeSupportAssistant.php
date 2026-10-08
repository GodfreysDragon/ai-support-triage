<?php

namespace App\Ai;

use App\Ai\Contracts\SupportAssistant;
use App\Ai\Data\TriageResult;
use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use Generator;
use Illuminate\Support\Str;

/**
 * Deterministic, offline stand-in for the real model. Used by the test suite
 * and when AI_DRIVER=fake, so the app can be demoed without an API key.
 */
class FakeSupportAssistant implements SupportAssistant
{
    public function __construct(
        private readonly int $chunkDelayMs = 0,
    ) {}

    public function triage(Ticket $ticket): TriageResult
    {
        $text = Str::lower($ticket->subject.' '.$ticket->body);

        $category = match (true) {
            Str::contains($text, ['invoice', 'charge', 'refund', 'billing', 'payment']) => TicketCategory::Billing,
            Str::contains($text, ['error', 'bug', 'broken', 'crash', '500']) => TicketCategory::Bug,
            Str::contains($text, ['password', 'login', 'sso', 'account']) => TicketCategory::Account,
            Str::contains($text, ['feature', 'would be great', 'wish']) => TicketCategory::FeatureRequest,
            Str::contains($text, ['how do i', 'how to', 'where can']) => TicketCategory::HowTo,
            default => TicketCategory::Other,
        };

        $angry = Str::contains($text, ['unacceptable', 'furious', 'cancel', '!!!']);

        return new TriageResult(
            category: $category,
            priority: match (true) {
                Str::contains($text, ['down', 'outage', 'urgent', 'data loss']) => TicketPriority::Urgent,
                $category === TicketCategory::Bug || $angry => TicketPriority::High,
                $category === TicketCategory::Billing => TicketPriority::Medium,
                default => TicketPriority::Low,
            },
            sentiment: $angry ? Sentiment::Angry : Sentiment::Neutral,
            summary: Str::limit("Customer writes about: {$ticket->subject}", 140),
            tags: [$category->value, 'demo'],
        );
    }

    public function streamReply(Ticket $ticket, ?string $guidance = null): Generator
    {
        $reply = "Hi there,\n\nThanks for reaching out about \"{$ticket->subject}\". "
            ."I'm sorry for the trouble, and I'm looking into it now.\n\n"
            .'Could you share any screenshots or the exact time this happened? '
            ."That will help us get to the bottom of it quickly.\n\n"
            .($guidance ? "(Agent guidance applied: {$guidance})\n\n" : '')
            ."Best regards,\nThe Support Team";

        // Split after each whitespace character so chunks arrive word by word.
        foreach (preg_split('/(?<=\s)/', $reply) ?: [$reply] as $chunk) {
            if ($this->chunkDelayMs > 0) {
                usleep($this->chunkDelayMs * 1000);
            }

            yield $chunk;
        }
    }
}
