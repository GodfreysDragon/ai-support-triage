# AI Support Triage

A starter kit for building AI-powered SaaS apps with **Laravel 13, Vue 3, Inertia v3 and the Claude API**.

Agents paste in a customer support ticket. A **queued job** classifies it with Claude using
**structured outputs** (category, priority, sentiment, summary, tags). The agent can then
**stream** a drafted reply token by token over **server-sent events**.

## What it demonstrates

| Concern                                         | Where                                                                                           |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Auth (login, 2FA, passkeys, email verification) | Laravel Vue starter kit (Fortify)                                                               |
| Per-user authorization                          | `app/Policies/TicketPolicy.php`                                                                 |
| Background AI work with retries and backoff     | `app/Jobs/TriageTicket.php`                                                                     |
| Schema-guaranteed JSON from the model           | `app/Ai/Data/TriageResult.php` → `outputConfig.format`                                          |
| Prompts kept as reviewable plain text           | `resources/prompts/` + `app/Ai/TicketPromptBuilder.php`                                         |
| Streaming LLM output to the browser (SSE)       | `app/Http/Controllers/TicketReplyController.php` + `resources/js/composables/useReplyDraft.ts`  |
| Live status updates without websockets          | Inertia `usePoll` while a ticket is pending                                                     |
| Rate limiting the endpoints that spend tokens   | `throttle:ai` limiter in `AppServiceProvider`                                                   |
| Swappable AI provider                           | `App\Ai\Contracts\SupportAssistant` (Claude implementation + offline fake)                      |
| Refusal handling and server-side fallback       | `ClaudeSupportAssistant` (`fallbacks: 'default'`)                                               |
| Tests that never hit the network                | `tests/Feature/` (the Claude tests record the real SDK's HTTP requests instead of sending them) |

## How a request flows

```
POST /tickets ──► Ticket (pending) ──► TriageTicket job ──► Claude (structured output) ──► Ticket (triaged)
                                                     ▲
                    Show.vue polls every 1.5s ───────┘

POST /tickets/{id}/reply ──► Claude stream ──► SSE {type:"delta"} … {type:"done"} ──► ReplyPanel appends text live
                                                                       └─► draft saved only if the stream completes
```

## Project layout

```
app/
  Ai/
    Contracts/SupportAssistant.php   the one interface every AI call goes through
    ClaudeSupportAssistant.php       Claude API implementation
    FakeSupportAssistant.php         offline implementation (tests, demos)
    TicketPromptBuilder.php          builds the messages; loads system prompts
    Data/TriageResult.php            triage result + its JSON schema
  Enums/                             status, category, priority, sentiment
  Http/
    Controllers/                     Ticket, TicketReply (SSE), Dashboard
    Requests/                        validation for new tickets and reply drafts
    Resources/TicketResource.php     the ticket fields sent to the browser
  Jobs/TriageTicket.php              queued triage with retry policy
  Models/Ticket.php                  status changes (markTriaged…) and scopes
config/ai.php                        driver, model, per-call token budget and effort
resources/
  prompts/                           system prompts as plain text
  js/
    pages/tickets/                   Index (form + queue), Show (ticket page)
    components/tickets/              TriageCard, ReplyPanel, NewTicketForm, TicketBadges
    composables/useReplyDraft.ts     reply streaming state
    types/tickets.ts                 TS types mirroring the PHP enums and TicketResource
```

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Then set your key in `.env`:

```dotenv
AI_DRIVER=claude          # or "fake" to run fully offline
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-5-5
AI_REQUESTS_PER_MINUTE=10 # per-user limit on the endpoints that call the model
```

Token budgets and effort for each call (triage vs. reply drafting) are in `config/ai.php`.

Run the web server, queue worker and Vite together:

```bash
composer run dev
```

Register an account, open **Tickets**, click one of the sample buttons, and submit.

> **Streaming and the dev server:** a reply stream holds its HTTP request open, so the server needs
> more than one worker or the page's polling requests wait behind it. `PHP_CLI_SERVER_WORKERS=4` in
> `.env` handles this for `php artisan serve` on macOS and Linux. **PHP's built-in server can't fork on
> Windows**, so the setting is ignored there; on Windows, serve the app through Herd (or any php-fpm
> setup) instead. In production, run behind php-fpm (or Octane) and keep `X-Accel-Buffering: no` (sent
> automatically) so nginx doesn't buffer the stream.

## Running without an API key

Set `AI_DRIVER=fake`. `FakeSupportAssistant` classifies with simple keyword rules and streams a canned
reply with a small delay between chunks (`AI_FAKE_CHUNK_DELAY_MS`), so the whole UI works offline. The
test suite always uses it.

## Tests

```bash
php artisan test         # Pest only
composer ci:check        # everything CI runs: formatting, lint, vue-tsc, Pint, PHPStan, Pest
```

## Design decisions

- **Polling instead of websockets.** A ticket is only pending for a few seconds, so polling while it's
  pending (and stopping after) avoids running a websocket server. See _Extending it_ to switch.
- **Drafts are saved only when the stream finishes.** A stopped, failed or refused stream leaves the
  previous draft in place, so a half-written reply is never stored.
- **Ticket text is untrusted.** It's wrapped in `<ticket>` tags and the system prompts tell the model
  not to follow instructions inside it.
- **Only transient errors are retried.** Rate limits, overloads and dropped connections go back to the
  queue with backoff; refusals, bad requests and malformed output fail at once (see
  `TriageTicket::isTransient()`).
- **A fake driver ships with the app.** Tests and demos run offline and for free, through the same
  interface the real driver uses.

## Extending it

- **Another provider:** implement `SupportAssistant` and bind it in `AppServiceProvider::register()`.
- **Another AI feature:** add a method to the contract, a queued job for anything slow, and an
  `eventStream` endpoint for anything a user watches being written.
- **Websockets instead of polling:** install Laravel Reverb, broadcast an event from `TriageTicket`,
  and replace `usePoll` with `useEcho`.
- **New ticket field:** add the column, then add it to `TicketResource` and the `Ticket` type in
  `resources/js/types/tickets.ts`. The props tests in `tests/Feature/TicketPropsTest.php` will remind you.
