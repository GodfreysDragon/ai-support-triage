# AI Support Triage

A starter kit for building AI-powered SaaS apps with **Laravel 13, Vue 3, Inertia v3 and the Claude API**.

Agents paste in a customer support ticket. A **queued job** classifies it with Claude using
**structured outputs** (category, priority, sentiment, summary, tags). The agent can then
**stream** a drafted reply token by token over **server-sent events**.

## What it demonstrates

| Concern | Where |
| --- | --- |
| Auth (login, 2FA, passkeys, email verification) | Laravel Vue starter kit (Fortify) |
| Per-user authorization | `app/Policies/TicketPolicy.php` |
| Background AI work with retries and backoff | `app/Jobs/TriageTicket.php` |
| Schema-guaranteed JSON from the model | `app/Ai/Data/TriageResult.php` → `outputConfig.format` |
| Streaming LLM output to the browser (SSE) | `app/Http/Controllers/TicketReplyController.php` + `useJsonEventStream` in `resources/js/pages/tickets/Show.vue` |
| Live status updates without websockets | Inertia `usePoll` while a ticket is pending |
| Rate limiting the endpoints that spend tokens | `throttle:ai` limiter in `AppServiceProvider` |
| Swappable AI provider | `App\Ai\Contracts\SupportAssistant` (Claude implementation + offline fake) |
| Refusal handling and server-side fallback | `ClaudeSupportAssistant` (`fallbacks: 'default'`) |
| Tests that never hit the network | `tests/Feature/TicketTest.php` |

## How a request flows

```
POST /tickets ──► Ticket (pending) ──► TriageTicket job ──► Claude (structured output) ──► Ticket (triaged)
                                                     ▲
                    Show.vue polls every 1.5s ───────┘

POST /tickets/{id}/reply ──► Claude stream ──► SSE {type:"delta"} … {type:"done"} ──► Show.vue appends text live
                                                                       └─► draft saved only if the stream completes
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
```

Run the web server, queue worker and Vite together:

```bash
composer run dev
```

Register an account, open **Tickets**, click one of the sample buttons, and submit.

> **Streaming and the dev server:** `PHP_CLI_SERVER_WORKERS=4` is set in `.env` so a long-running
> stream doesn't block the polling requests. In production, run behind php-fpm (or Herd/Valet/Octane)
> and keep `X-Accel-Buffering: no` (sent automatically) so nginx doesn't buffer the stream.

## Running without an API key

Set `AI_DRIVER=fake`. `FakeSupportAssistant` classifies with simple keyword rules and streams a canned
reply with a small delay between chunks (`AI_FAKE_CHUNK_DELAY_MS`), so the whole UI works offline. The
test suite always uses it.

## Tests

```bash
php artisan test
```

## Extending it

- **Another provider:** implement `SupportAssistant` and bind it in `AppServiceProvider::register()`.
- **Another AI feature:** add a method to the contract, a queued job for anything slow, and an
  `eventStream` endpoint for anything a user watches being written.
- **Websockets instead of polling:** install Laravel Reverb, broadcast an event from `TriageTicket`,
  and replace `usePoll` with `useEcho`.
