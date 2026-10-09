<?php

use App\Demo\SampleTickets;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

test('Try the demo signs the visitor in to a fresh account with sample tickets', function () {
    $this->post(route('demo'))->assertRedirect(route('dashboard'));

    $user = Auth::user();
    expect($user->is_demo)->toBeTrue()
        ->and($user->email)->toEndWith('@demo.invalid')
        ->and($user->tickets()->count())->toBe(count(SampleTickets::all()))
        ->and($user->tickets()->where('status', TicketStatus::Failed)->count())->toBe(1)
        ->and($user->tickets()->whereNotNull('draft_reply')->count())->toBe(2);

    $this->get(route('dashboard'))->assertOk();
});

test('each visitor gets their own demo account', function () {
    $this->post(route('demo'));
    $first = Auth::user();
    Auth::logout();

    $this->post(route('demo'));

    expect(Auth::id())->not->toBe($first->id)
        ->and(User::where('is_demo', true)->count())->toBe(2);
});

test('the newest sample ticket appears first in the queue', function () {
    $this->post(route('demo'));

    $this->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tickets.data.0.subject', SampleTickets::all()[0]['subject']));
});

test('expired demo accounts are pruned, real accounts never are', function () {
    $expired = User::factory()->create(['created_at' => now()->subHours(25)])->forceFill(['is_demo' => true]);
    $expired->save();
    Ticket::factory()->for($expired)->create();
    $fresh = User::factory()->create()->forceFill(['is_demo' => true]);
    $fresh->save();
    $oldRealUser = User::factory()->create(['created_at' => now()->subYear()]);

    $this->artisan('demo:prune')->expectsOutputToContain('Deleted 1 expired demo account(s).');

    expect(User::find($expired->id))->toBeNull()
        ->and(Ticket::where('user_id', $expired->id)->exists())->toBeFalse()
        ->and(User::find($fresh->id))->not->toBeNull()
        ->and(User::find($oldRealUser->id))->not->toBeNull();
});

test('starting a demo also prunes expired demo accounts', function () {
    $expired = User::factory()->create(['created_at' => now()->subHours(25)])->forceFill(['is_demo' => true]);
    $expired->save();

    $this->post(route('demo'));

    expect(User::find($expired->id))->toBeNull();
});

test('signed-in users are not given a demo account', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('demo'))
        ->assertRedirect(route('dashboard'));

    expect(User::where('is_demo', true)->exists())->toBeFalse();
});

test('demo sign-ups are rate limited per visitor', function () {
    config(['demo.per_minute' => 2]);

    foreach (range(1, 2) as $attempt) {
        $this->post(route('demo'));
        Auth::logout();
    }

    $this->post(route('demo'))->assertTooManyRequests();
});

test('demo mode can be switched off', function () {
    config(['demo.enabled' => false]);

    $this->post(route('demo'))->assertNotFound();
});

test('the landing page offers the demo when it is enabled', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('demoEnabled', true));
});

test('new accounts can use the app straight away, without email verification', function () {
    $this->post(route('register.store'), [
        'name' => 'Pat Example',
        'email' => 'pat@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->get(route('tickets.index'))->assertOk();
});
