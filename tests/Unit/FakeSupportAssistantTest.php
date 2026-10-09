<?php

use App\Ai\FakeSupportAssistant;
use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Ticket;

function fakeTicket(string $subject, string $body, ?TicketCategory $category = null): Ticket
{
    return new Ticket(['subject' => $subject, 'body' => $body, 'category' => $category]);
}

function fakeReply(Ticket $ticket, ?string $guidance = null): string
{
    return implode('', iterator_to_array((new FakeSupportAssistant)->streamReply($ticket, $guidance), false));
}

test('triage classifies by keywords and summarises with the first sentence', function () {
    $result = (new FakeSupportAssistant)->triage(fakeTicket(
        'Checkout fails for every customer since the 2pm deploy',
        'Every checkout has failed since 2pm. We are losing sales by the minute.',
    ));

    expect($result->category)->toBe(TicketCategory::Bug)
        ->and($result->priority)->toBe(TicketPriority::Urgent)
        ->and($result->summary)->toBe('Every checkout has failed since 2pm.')
        ->and($result->tags)->toBe(['checkout', 'deploy']);
});

test('triage reads the customer’s mood', function (string $body, Sentiment $sentiment) {
    expect((new FakeSupportAssistant)->triage(fakeTicket('Hello', $body))->sentiment)->toBe($sentiment);
})->with([
    'angry' => ['This is unacceptable, we will cancel.', Sentiment::Angry],
    'negative' => ['This is the second time, which is frustrating.', Sentiment::Negative],
    'positive' => ['Love the product, thanks!', Sentiment::Positive],
    'neutral' => ['Where is the export button?', Sentiment::Neutral],
]);

test('keywords match whole words, not parts of words', function () {
    $result = (new FakeSupportAssistant)->triage(fakeTicket(
        'Private download link',
        'Where can I find the download link for my curious team?',
    ));

    expect($result->category)->toBe(TicketCategory::HowTo)  // not billing ("private" contains "vat")
        ->and($result->priority)->toBe(TicketPriority::Low)  // not urgent ("download" contains "down")
        ->and($result->tags)->not->toContain('ios');         // "curious" contains "ios"
});

test('replies use a template for the ticket’s category', function () {
    $billing = fakeReply(fakeTicket('Charged twice', 'Refund please.', TicketCategory::Billing));
    $bug = fakeReply(fakeTicket('App crashes', 'It crashes.', TicketCategory::Bug));

    expect($billing)->toStartWith("Hi there,\n\nThanks for reaching out about \"Charged twice\".")
        ->toContain('billing team')
        ->toEndWith('The Support Team')
        ->and($bug)->toContain('engineering team');
});

test('recognised guidance changes the reply instead of being echoed', function () {
    $ticket = fakeTicket('Charged twice', 'Refund please.', TicketCategory::Billing);
    $plain = fakeReply($ticket);
    $guided = fakeReply($ticket, 'Apologise, offer a credit, keep it short');

    expect($guided)->toContain("I'm really sorry about this")
        ->toContain('add a credit to your account')
        ->not->toContain('Could you send the invoice number')  // "keep it short" drops the question
        ->not->toContain('Apologise, offer a credit')          // guidance is never echoed
        ->and($plain)->toContain('Could you send the invoice number');
});
