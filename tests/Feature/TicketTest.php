<?php

use App\Ai\Contracts\SupportAssistant;
use App\Ai\Data\TriageResult;
use App\Ai\Exceptions\AssistantRefusedException;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * A SupportAssistant that refuses everything, to exercise failure paths.
 */
function refusingAssistant(): SupportAssistant
{
    return new class implements SupportAssistant
    {
        public function triage(Ticket $ticket): TriageResult
        {
            throw AssistantRefusedException::withCategory('cyber');
        }

        public function streamReply(Ticket $ticket, ?string $guidance = null): Generator
        {
            yield 'Partial ';

            throw AssistantRefusedException::withCategory('cyber');
        }
    };
}

/**
 * Parse an SSE body into the JSON payloads of each event.
 *
 * @return list<mixed>
 */
function sseEvents(string $body): array
{
    preg_match_all('/^data: (.*)$/m', $body, $matches);

    return collect($matches[1])
        ->reject(fn (string $data) => $data === '</stream>')
        ->map(fn (string $data) => json_decode($data, true))
        ->values()
        ->all();
}

test('guests cannot access tickets', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
    $this->post(route('tickets.store'))->assertRedirect(route('login'));
});

test('creating a ticket queues it for triage', function () {
    Queue::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tickets.store'), [
        'customer_email' => 'jane@example.com',
        'subject' => 'Charged twice',
        'body' => 'My card was charged twice for the March invoice.',
    ]);

    $ticket = $user->tickets()->sole();
    $response->assertRedirect(route('tickets.show', $ticket));
    expect($ticket->status)->toBe(TicketStatus::Pending);
    Queue::assertPushed(TriageTicket::class, fn (TriageTicket $job) => $job->ticket->is($ticket));
});

test('ticket creation is validated', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tickets.store'), ['customer_email' => 'not-an-email'])
        ->assertSessionHasErrors(['subject', 'body', 'customer_email']);
});

test('the triage job stores the classification', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Production is down',
        'body' => 'Every request returns a 500 error since the deploy. This is an outage!',
    ]);

    TriageTicket::dispatchSync($ticket);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Triaged)
        ->and($ticket->category)->toBe(TicketCategory::Bug)
        ->and($ticket->priority)->toBe(TicketPriority::Urgent)
        ->and($ticket->summary)->not->toBeEmpty()
        ->and($ticket->triaged_at)->not->toBeNull();
});

test('a refused triage marks the ticket failed without retrying', function () {
    app()->instance(SupportAssistant::class, refusingAssistant());
    $ticket = Ticket::factory()->create();

    TriageTicket::dispatchSync($ticket);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Failed)
        ->and($ticket->error)->toContain('declined');
});

test('users cannot see or act on other users tickets', function () {
    $ticket = Ticket::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($intruder)->post(route('tickets.reply', $ticket))->assertForbidden();
    $this->actingAs($intruder)->post(route('tickets.retriage', $ticket))->assertForbidden();
});

test('the reply endpoint streams deltas and saves the finished draft', function () {
    $ticket = Ticket::factory()->triaged()->create(['subject' => 'Export is slow']);

    $response = $this->actingAs($ticket->user)
        ->post(route('tickets.reply', $ticket), ['guidance' => 'Offer a credit']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = sseEvents($response->streamedContent());
    $deltas = collect($events)->where('type', 'delta');
    $text = $deltas->pluck('text')->implode('');

    expect($deltas->count())->toBeGreaterThan(1)
        ->and($text)->toContain('Export is slow')->toContain('add a credit to your account')
        ->and(end($events))->toBe(['type' => 'done'])
        ->and($ticket->refresh()->draft_reply)->toBe($text);
});

test('a refused reply streams an error and discards the partial draft', function () {
    app()->instance(SupportAssistant::class, refusingAssistant());
    $ticket = Ticket::factory()->triaged()->create();

    $events = sseEvents($this->actingAs($ticket->user)
        ->post(route('tickets.reply', $ticket))
        ->streamedContent());

    expect(end($events)['type'])->toBe('error')
        ->and($ticket->refresh()->draft_reply)->toBeNull();
});

test('the AI endpoints are rate limited per user', function () {
    config(['ai.rate_limits.per_minute' => 2]);
    $ticket = Ticket::factory()->triaged()->create();

    $this->actingAs($ticket->user);
    $this->post(route('tickets.retriage', $ticket))->assertRedirect();
    $this->post(route('tickets.retriage', $ticket))->assertRedirect();
    $this->post(route('tickets.retriage', $ticket))->assertTooManyRequests();
});
