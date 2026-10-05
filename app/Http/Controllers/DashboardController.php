<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Queue health at a glance.
     */
    public function __invoke(Request $request): Response
    {
        $tickets = $request->user()->tickets();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (clone $tickets)->count(),
                'pending' => (clone $tickets)->where('status', TicketStatus::Pending)->count(),
                'urgent' => (clone $tickets)->where('priority', TicketPriority::Urgent)->count(),
                'failed' => (clone $tickets)->where('status', TicketStatus::Failed)->count(),
            ],
            'byCategory' => (clone $tickets)->toBase()
                ->whereNotNull('category')
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->orderByDesc('count')
                ->pluck('count', 'category'),
            'recent' => (clone $tickets)->latest()->limit(5)->get()
                ->map(fn (Ticket $ticket) => TicketController::present($ticket)),
        ]);
    }
}
