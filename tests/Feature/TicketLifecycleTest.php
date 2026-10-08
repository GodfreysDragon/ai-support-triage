<?php

use Anthropic\Core\Exceptions\RateLimitException;
use App\Ai\Contracts\SupportAssistant;
use App\Ai\Data\TriageResult;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('retrying triage resets a failed ticket and queues it again', function () {
    Queue::fake();
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create([
        'status' => TicketStatus::Failed,
        'error' => 'Triage failed.',
    ]);

    $this->actingAs($user)->post(route('tickets.retriage', $ticket))->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Pending)
        ->and($ticket->error)->toBeNull();
    Queue::assertPushed(TriageTicket::class, fn (TriageTicket $job) => $job->ticket->is($ticket));
});

test('transient API errors are rethrown so the queue retries them', function () {
    $assistant = new class implements SupportAssistant
    {
        public function triage(Ticket $ticket): TriageResult
        {
            throw new RateLimitException(
                new Request('POST', 'https://api.anthropic.com/v1/messages'),
                new Response(429, [], '{"error":{"type":"rate_limit_error"}}'),
            );
        }

        public function streamReply(Ticket $ticket, ?string $guidance = null): Generator
        {
            yield '';
        }
    };
    $ticket = Ticket::factory()->create();

    expect(fn () => (new TriageTicket($ticket))->handle($assistant))->toThrow(RateLimitException::class);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Pending);
});

test('the dashboard counts only the user\'s tickets by status and priority', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user)->count(2)->create();
    Ticket::factory()->for($user)->create(['status' => TicketStatus::Failed]);
    Ticket::factory()->for($user)->triaged()->create(['priority' => TicketPriority::Urgent, 'category' => 'billing']);
    Ticket::factory()->for($user)->triaged()->create(['priority' => TicketPriority::Low, 'category' => 'billing']);
    Ticket::factory()->triaged()->create(['priority' => TicketPriority::Urgent]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', ['total' => 5, 'pending' => 2, 'urgent' => 1, 'failed' => 1])
            ->where('byCategory', ['billing' => 2])
            ->has('recent', 5));
});
