<?php

use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The ticket fields the frontend's Ticket type (resources/js/types/tickets.ts)
 * expects. Every page that sends a ticket must send exactly these.
 */
function assertTicketShape(Ticket $ticket): Closure
{
    return fn (Assert $json) => $json
        ->where('id', $ticket->id)
        ->where('subject', $ticket->subject)
        ->where('status', $ticket->status->value)
        ->where('tags', $ticket->tags ?? [])
        ->where('triaged_at', $ticket->triaged_at?->toIso8601String())
        ->where('created_at', $ticket->created_at->toIso8601String())
        ->hasAll([
            'customer_email', 'body', 'category', 'priority', 'sentiment',
            'summary', 'error', 'draft_reply',
        ]);
}

test('the ticket list sends paginated tickets in the frontend shape', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->triaged()->create();

    $this->actingAs($user)->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Index')
            ->has('tickets.data', 1, assertTicketShape($ticket))
            ->where('tickets.current_page', 1)
            ->where('tickets.last_page', 1)
            ->where('tickets.total', 1)
            ->has('tickets.prev_page_url')
            ->has('tickets.next_page_url'));
});

test('the ticket page sends the ticket in the frontend shape', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $this->actingAs($user)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Show')
            ->has('ticket', assertTicketShape($ticket)));
});

test('the dashboard sends recent tickets in the frontend shape', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->triaged()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recent', 1, assertTicketShape($ticket))
            ->where('stats.total', 1));
});
