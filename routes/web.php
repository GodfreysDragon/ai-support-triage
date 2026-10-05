<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

    // Endpoints that spend tokens are rate limited per user.
    Route::middleware('throttle:ai')->group(function () {
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::post('tickets/{ticket}/retriage', [TicketController::class, 'retriage'])->name('tickets.retriage');
        Route::post('tickets/{ticket}/reply', TicketReplyController::class)->name('tickets.reply');
    });
});

require __DIR__.'/settings.php';
