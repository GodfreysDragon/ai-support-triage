# Project Summary: AI Support Triage

## In one sentence

A full-stack SaaS starter app where support agents submit customer tickets, Claude classifies them in the background, and agents get a reply draft that streams into the browser as it's written.

## The problem it solves

Support teams lose time reading every ticket to decide how urgent it is and where it belongs, then writing the same kinds of replies over and over. This app does the first pass automatically:

- **Triage:** every ticket gets a category, a priority, the customer's sentiment, a one-line summary and tags.
- **Drafting:** the agent can ask for a reply draft, steer it with guidance ("offer a credit, keep it short"), then review and send it.

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | Vue 3 + TypeScript, Inertia v3, Tailwind CSS v4, shadcn-vue components |
| AI | Claude API via the official Anthropic PHP SDK (default model `claude-opus-5-5`) |
| Background work | Laravel queues (database driver) |
| Streaming | Server-sent events (`response()->eventStream()` + `@laravel/stream-vue`) |
| Auth | Laravel Vue starter kit (Fortify): login, 2FA, passkeys, email verification |
| Database | SQLite by default (any Laravel-supported database works) |
| Tests | Pest |

## Features

1. **Ticket intake:** a form with email, subject and message, plus three sample tickets for quick demos.
2. **Background AI triage:** a queued job calls Claude with a fixed JSON schema, so the result always has the right shape. It retries temporary failures (rate limits, outages) with backoff and marks the ticket failed on permanent ones. A failed ticket has a "Retry triage" button.
3. **Live status updates:** pages poll every 1.5–2 seconds only while a ticket is still pending, then stop.
4. **Streaming reply drafts:** text appears word by word. The agent can stop, regenerate or copy the draft. Drafts are saved only when the stream finishes; a partial or refused draft is discarded.
5. **Dashboard:** totals for all tickets, pending, urgent and failed, a breakdown by category, and recent tickets.
6. **Safeguards:**
   - Users can only see and act on their own tickets.
   - Each user is limited to 10 AI requests per minute (configurable).
   - Ticket text is treated as untrusted input, so the model is told not to follow instructions inside it.
   - Server-side refusal fallback: if Claude declines, the API retries on a fallback model.
7. **Offline mode:** `AI_DRIVER=fake` swaps in a rule-based assistant, so you can run the whole app and its tests without an API key.

## Architecture

```
Browser (Vue + Inertia)
   │  POST /tickets                         POST /tickets/{id}/reply
   ▼                                          ▼
TicketController ──dispatch──► TriageTicket job      TicketReplyController
                                  │                     │  (SSE stream)
                                  ▼                     ▼
                     SupportAssistant interface  ◄──────┘
                        ├── ClaudeSupportAssistant  (Anthropic SDK)
                        └── FakeSupportAssistant    (offline / tests)
```

All AI calls go through one interface (`app/Ai/Contracts/SupportAssistant.php`), so another provider such as OpenAI can be added by writing one class and changing one binding in `AppServiceProvider`.

## Key files

| File | Purpose |
| --- | --- |
| `app/Ai/ClaudeSupportAssistant.php` | Claude calls: structured triage and the streamed reply |
| `app/Ai/FakeSupportAssistant.php` | Offline stand-in used in tests and demos |
| `app/Ai/Data/TriageResult.php` | Triage data object and its JSON schema |
| `app/Jobs/TriageTicket.php` | Queued triage with retry and failure handling |
| `app/Http/Controllers/TicketReplyController.php` | SSE streaming endpoint |
| `app/Http/Controllers/TicketController.php` | List, create, show and re-triage tickets |
| `app/Http/Controllers/DashboardController.php` | Dashboard stats |
| `app/Policies/TicketPolicy.php` | Ownership checks |
| `config/ai.php` | Driver, model, API key and rate-limit settings |
| `resources/js/pages/tickets/Index.vue` | Ticket form and queue |
| `resources/js/pages/tickets/Show.vue` | Triage card and streaming reply panel |
| `resources/js/pages/Dashboard.vue` | Stats dashboard |
| `tests/Feature/TicketTest.php` | Feature tests for the AI flows |

## Configuration

```dotenv
AI_DRIVER=claude              # "claude" or "fake"
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-5-5
AI_REQUESTS_PER_MINUTE=10
AI_FAKE_CHUNK_DELAY_MS=40     # fake driver only: delay between streamed chunks
```

## Quick start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer run dev              # web server + queue worker + Vite
```

Then register an account, open **Tickets**, load a sample and submit it.

## Testing status

- **Test suite:** 49 tests pass. They include 9 new feature tests for ticket creation, validation, triage, refusals, access control, streaming and rate limiting.
- **Frontend:** the TypeScript check, linter and production build all pass.
- **Live smoke test:** login, ticket creation, queued triage and the SSE stream were checked over real HTTP using the offline driver.
- **Not yet verified:** a call to the real Claude API, because no API key was available during development.

## Possible next steps

- Push updates over websockets (Laravel Reverb) instead of polling.
- Add team workspaces so several agents can share a queue.
- Send approved replies by email and keep a history of past drafts.
- Track per-user token usage and cost for billing.
- Add OpenAI as a second provider behind the same interface.
