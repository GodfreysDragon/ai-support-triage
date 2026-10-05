<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * List the user's tickets, newest first.
     */
    public function index(Request $request): Response
    {
        $tickets = $request->user()->tickets()
            ->latest()
            ->paginate(15)
            ->through(fn (Ticket $ticket) => self::present($ticket));

        return Inertia::render('tickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * Store a new ticket and queue it for AI triage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket = $request->user()->tickets()->create($validated);

        TriageTicket::dispatch($ticket);

        return to_route('tickets.show', $ticket);
    }

    /**
     * Show a ticket with its triage result and reply drafting panel.
     */
    public function show(Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        return Inertia::render('tickets/Show', [
            'ticket' => self::present($ticket),
        ]);
    }

    /**
     * Re-run triage (e.g. after a failure).
     */
    public function retriage(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $ticket->update(['status' => TicketStatus::Pending, 'error' => null]);

        TriageTicket::dispatch($ticket);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'customer_email' => $ticket->customer_email,
            'subject' => $ticket->subject,
            'body' => $ticket->body,
            'status' => $ticket->status,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'sentiment' => $ticket->sentiment,
            'summary' => $ticket->summary,
            'tags' => $ticket->tags ?? [],
            'error' => $ticket->error,
            'draft_reply' => $ticket->draft_reply,
            'triaged_at' => $ticket->triaged_at?->toIso8601String(),
            'created_at' => $ticket->created_at->toIso8601String(),
        ];
    }
}
