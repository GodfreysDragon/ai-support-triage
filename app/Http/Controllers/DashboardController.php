<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
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
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => $user->tickets()->count(),
                'pending' => $user->tickets()->pending()->count(),
                'urgent' => $user->tickets()->urgent()->count(),
                'failed' => $user->tickets()->failed()->count(),
            ],
            'byCategory' => $user->tickets()->toBase()
                ->whereNotNull('category')
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->orderByDesc('count')
                ->pluck('count', 'category'),
            'recent' => $user->tickets()->latest()->limit(5)->get()
                ->map(fn (Ticket $ticket) => TicketResource::make($ticket)->resolve()),
        ]);
    }
}
