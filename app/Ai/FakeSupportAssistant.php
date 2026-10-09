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
 * Deterministic, offline stand-in for the real model. Used by the test suite,
 * the public demo and whenever AI_DRIVER=fake, so the app runs without an API
 * key or cost. Keyword rules classify tickets; replies come from a template per
 * category, adjusted by recognised guidance ("keep it short", "offer a credit"...).
 */
class FakeSupportAssistant implements SupportAssistant
{
    /**
     * Keywords that become tags when they appear in a ticket, in priority order.
     * (PHP turns the numeric key "500" into an int, hence array-key.)
     *
     * @var array<array-key, string>
     */
    private const TAGS = [
        'checkout' => 'checkout', 'payment*' => 'payments', 'invoice*' => 'invoice', 'refund*' => 'refund',
        'charge*' => 'billing', 'vat' => 'tax', 'sso' => 'sso', 'okta' => 'okta', 'password*' => 'password',
        'log in' => 'login', 'login*' => 'login', 'admin*' => 'permissions', 'export*' => 'export', 'csv' => 'csv',
        'dashboard*' => 'dashboard', 'mobile' => 'mobile', 'ios' => 'ios', 'android' => 'android',
        'crash*' => 'crash', '500' => 'server-error', 'outage*' => 'outage', 'deploy*' => 'deploy',
    ];

    /**
     * Per category: what we're doing about it, and what we need from the customer.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const REPLIES = [
        'billing' => [
            "I've passed this to our billing team to review. They'll confirm what happened and correct anything that's wrong.",
            'Could you send the invoice number and the date of the charge?',
        ],
        'bug' => [
            "I've logged this with our engineering team so they can investigate.",
            'Could you share the steps that lead to the problem, any error message you see, and roughly when it started?',
        ],
        'account' => [
            "I've asked our accounts team to take a look at your sign-in.",
            "Could you confirm the email address on the account and which sign-in method you're using?",
        ],
        'feature_request' => [
            "I've shared your suggestion with our product team. Requests like this directly shape what we build next.",
            "If you can tell us a bit more about how you'd use it, that helps them prioritise.",
        ],
        'how_to' => [
            "Happy to help. I'm putting together the steps for you now.",
            "Let me know which plan you're on, so I can point you to the right setting.",
        ],
        'other' => [
            "I've read your message and passed it to the right person on our team.",
            'If there is anything else we should know, just reply to this email.',
        ],
    ];

    public function __construct(
        private readonly int $chunkDelayMs = 0,
    ) {}

    public function triage(Ticket $ticket): TriageResult
    {
        $text = Str::lower($ticket->subject.' '.$ticket->body);

        $category = match (true) {
            self::mentions($text, ['invoice*', 'charge*', 'refund*', 'billing', 'payment*', 'vat']) => TicketCategory::Billing,
            self::mentions($text, ['error*', 'bug*', 'broken', 'crash*', '500', 'fail*']) => TicketCategory::Bug,
            self::mentions($text, ['password*', 'login*', 'log in', 'sso', 'account*']) => TicketCategory::Account,
            self::mentions($text, ['feature*', 'would be great', 'wish', 'is there a way']) => TicketCategory::FeatureRequest,
            self::mentions($text, ['how do i', 'how to', 'where can', 'where is']) => TicketCategory::HowTo,
            default => TicketCategory::Other,
        };

        $sentiment = match (true) {
            self::mentions($text, ['unacceptable', 'furious', 'cancel*']) || Str::contains($text, '!!!') => Sentiment::Angry,
            self::mentions($text, ['frustrat*', 'annoy*', 'disappoint*', 'second time', 'asap']) => Sentiment::Negative,
            self::mentions($text, ['love*', 'great', 'thank*']) => Sentiment::Positive,
            default => Sentiment::Neutral,
        };

        return new TriageResult(
            category: $category,
            priority: match (true) {
                self::mentions($text, ['down', 'outage*', 'urgent*', 'data loss', 'every customer']) => TicketPriority::Urgent,
                $category === TicketCategory::Bug || $sentiment === Sentiment::Angry => TicketPriority::High,
                $category === TicketCategory::Billing || $category === TicketCategory::Account => TicketPriority::Medium,
                default => TicketPriority::Low,
            },
            sentiment: $sentiment,
            summary: self::firstSentence($ticket->body) ?: $ticket->subject,
            tags: self::tags($text, $category),
        );
    }

    public function streamReply(Ticket $ticket, ?string $guidance = null): Generator
    {
        $reply = self::reply($ticket, Str::lower((string) $guidance));

        // Split after each whitespace character so chunks arrive word by word.
        foreach (preg_split('/(?<=\s)/', $reply) ?: [$reply] as $chunk) {
            if ($this->chunkDelayMs > 0) {
                usleep($this->chunkDelayMs * 1000);
            }

            yield $chunk;
        }
    }

    /**
     * Builds the reply from the ticket's category template. Guidance isn't
     * understood, but common intents are recognised so the draft visibly reacts.
     */
    private static function reply(Ticket $ticket, string $guidance): string
    {
        [$action, $ask] = self::REPLIES[($ticket->category ?? TicketCategory::Other)->value];
        $wants = fn (string ...$words): bool => self::mentions($guidance, $words);

        $opening = $wants('apolog*', 'sorry')
            ? "I'm really sorry about this, and thank you for your patience while we sort it out."
            : "Thanks for reaching out about \"{$ticket->subject}\".";

        $extras = array_filter([
            $wants('escalat*', 'urgent*', 'priority') ? "I've escalated this to our senior team as a priority." : null,
            $wants('credit*', 'refund*', 'discount*', 'compensat*') ? "As a thank-you for your patience, we'll add a credit to your account once this is resolved." : null,
            $wants('timeline*', 'eta', 'when', 'update*') ? "We'll update you within one business day." : null,
        ]);

        $paragraphs = [$opening.' '.$action];
        if ($extras !== []) {
            $paragraphs[] = implode(' ', $extras);
        }
        if (! $wants('short*', 'brief*', 'concise*')) {
            $paragraphs[] = $ask;
        }

        return "Hi there,\n\n".implode("\n\n", $paragraphs)."\n\nThe Support Team";
    }

    /**
     * Whole-word match against any of the words; a trailing * makes a word a
     * prefix ("frustrat*" matches frustrated, frustrating). Plain substring
     * matching misfires: "vat" is in "private", "down" in "download".
     *
     * @param  array<string>  $words
     */
    private static function mentions(string $text, array $words): bool
    {
        $patterns = array_map(
            fn (string $word): string => str_ends_with($word, '*')
                ? preg_quote(rtrim($word, '*'), '/').'\w*'
                : preg_quote($word, '/'),
            $words,
        );

        return (bool) preg_match('/\b(?:'.implode('|', $patterns).')\b/u', $text);
    }

    private static function firstSentence(string $body): string
    {
        $body = Str::squish($body);

        return Str::limit(preg_match('/^.+?[.!?](?=\s|$)/', $body, $match) ? $match[0] : $body, 140);
    }

    /**
     * @return list<string>
     */
    private static function tags(string $text, TicketCategory $category): array
    {
        $tags = [];
        foreach (self::TAGS as $keyword => $tag) {
            if (self::mentions($text, [(string) $keyword]) && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags ?: [str_replace('_', '-', $category->value)], 0, 4);
    }
}
