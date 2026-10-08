<?php

namespace App\Jobs;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Ai\Contracts\SupportAssistant;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Classifies a newly created (or retried) ticket in the background, so the
 * request that created it returns immediately. The ticket page polls until
 * the status leaves "pending".
 */
class TriageTicket implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * One first attempt plus one retry per backoff() step.
     */
    public int $tries = 4;

    public function __construct(public Ticket $ticket) {}

    /**
     * Seconds to wait between retries, growing so rate limits and overloads
     * have time to ease off.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * Only one triage per ticket may be queued at a time, so a double-clicked
     * "Retry triage" doesn't spend tokens twice.
     */
    public function uniqueId(): string
    {
        return (string) $this->ticket->id;
    }

    public function handle(SupportAssistant $assistant): void
    {
        try {
            $result = $assistant->triage($this->ticket);
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            // Transient: the SDK already retried a couple of times; let the
            // queue retry later with backoff.
            throw $e;
        } catch (Throwable $e) {
            // Refusals, bad requests, auth errors and malformed output won't
            // get better on retry.
            $this->fail($e);

            return;
        }

        $this->ticket->update([
            ...$result->toAttributes(),
            'status' => TicketStatus::Triaged,
            'triaged_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * Runs after the last retry, or straight away for a permanent error.
     * The message is shown to the agent next to the "Retry triage" button.
     */
    public function failed(?Throwable $exception): void
    {
        $this->ticket->update([
            'status' => TicketStatus::Failed,
            'error' => $exception?->getMessage() ?? 'Triage failed.',
        ]);
    }
}
