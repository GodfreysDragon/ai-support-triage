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

class TriageTicket implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 4;

    /**
     * Create a new job instance.
     */
    public function __construct(public Ticket $ticket) {}

    /**
     * Seconds to wait between retries (rate limits and overloads ease off).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * Only one triage per ticket may be queued at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->ticket->id;
    }

    /**
     * Execute the job.
     */
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
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->ticket->update([
            'status' => TicketStatus::Failed,
            'error' => $exception?->getMessage() ?? 'Triage failed.',
        ]);
    }
}
