<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/*
| Browser tests drive the real pages in Chromium (via Playwright). The queue
| runs synchronously in tests, so a new ticket is triaged before its page loads.
|
| Streaming itself isn't tested here: the plugin's test server buffers each
| response, and Laravel's eventStream() flushes that buffer after every event,
| so the browser receives an empty stream. Reply streaming is covered by
| tests/Feature/TicketTest.php (server) and useReplyDraft.test.ts (client).
*/

test('the landing page names the app and invites guests in', function () {
    visit('/')
        ->assertSee(config('app.name'))
        ->assertSee('Never fall behind on support again.')
        ->assertSee('Try the demo')
        ->assertSee('Create an account')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

test('Try the demo opens a dashboard full of sample tickets', function () {
    visit('/')
        ->click('Try the demo')
        ->assertPathIs('/dashboard')
        ->assertSee("You're exploring a demo account")
        ->assertSee('The AI is simulated')
        ->assertSee('Checkout fails for every customer since the 2pm deploy')
        ->assertNoJavaScriptErrors();
});

test('signed-in users are sent from the landing page to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    visit('/')
        ->assertSee('Go to dashboard')
        ->click('Go to dashboard')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('a guest can log in from the landing page', function () {
    $user = User::factory()->create();

    visit('/')
        ->click('Log in')
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('a sample ticket fills the form, is submitted and comes back triaged', function () {
    $this->actingAs(User::factory()->create());

    visit(route('tickets.index'))
        ->press('1')
        ->assertValue('#subject', 'Dashboard returns 500 since this morning')
        ->press('Submit for triage')
        ->assertPathBeginsWith('/tickets/')
        ->assertSee('Customer writes about: Dashboard returns 500 since this morning')
        ->assertSee('High') // the fake driver's keyword rules (the real model says urgent)
        ->assertNoJavaScriptErrors();
});

test('a failed ticket shows the error and can be retried', function () {
    $this->actingAs($user = User::factory()->create());
    $ticket = Ticket::factory()->for($user)->create([
        'subject' => 'Cannot log in',
        'status' => TicketStatus::Failed,
        'error' => 'The AI service is unavailable.',
    ]);

    visit(route('tickets.show', $ticket))
        ->assertSee('Triage failed')
        ->assertSee('The AI service is unavailable.')
        ->press('Retry triage')
        ->assertSee('Customer writes about: Cannot log in')
        ->assertDontSee('Triage failed')
        ->assertNoJavaScriptErrors();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Triaged);
});

test('a saved draft is shown with Regenerate and Copy', function () {
    $this->actingAs($user = User::factory()->create());
    $ticket = Ticket::factory()->for($user)->triaged()->create([
        'draft_reply' => 'Thanks for your patience. The Support Team',
    ]);

    visit(route('tickets.show', $ticket))
        ->assertSee('Thanks for your patience. The Support Team')
        ->assertSee('Regenerate')
        ->assertSee('Copy')
        ->assertDontSee('Restore saved draft')
        ->assertNoJavaScriptErrors();
});

test('the dashboard and queue list the user’s tickets', function () {
    $this->actingAs($user = User::factory()->create());
    Ticket::factory()->for($user)->triaged()->create(['subject' => 'Charged twice for September']);

    visit(route('dashboard'))
        ->assertSee('Charged twice for September')
        ->assertNoJavaScriptErrors();

    visit(route('tickets.index'))
        ->assertSee('Charged twice for September')
        ->assertSee('1 ticket')
        ->assertNoJavaScriptErrors();
});
