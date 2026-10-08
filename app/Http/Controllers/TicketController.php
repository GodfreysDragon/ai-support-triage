<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
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
            ->through(fn (Ticket $ticket) => TicketResource::make($ticket)->resolve());

        return Inertia::render('tickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * Store a new ticket and queue it for AI triage.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $request->user()->tickets()->create($request->validated());

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
            'ticket' => TicketResource::make($ticket)->resolve(),
        ]);
    }

    /**
     * Re-run triage (e.g. after a failure).
     */
    public function retriage(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $ticket->markPending();

        TriageTicket::dispatch($ticket);

        return back();
    }
}
