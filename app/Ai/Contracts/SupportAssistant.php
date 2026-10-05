<?php

namespace App\Ai\Contracts;

use App\Ai\Data\TriageResult;
use App\Ai\Exceptions\AssistantRefusedException;
use App\Models\Ticket;
use Generator;

interface SupportAssistant
{
    /**
     * Classify a ticket (category, priority, sentiment, summary, tags).
     *
     * @throws AssistantRefusedException
     */
    public function triage(Ticket $ticket): TriageResult;

    /**
     * Stream a drafted reply to the customer, one text chunk at a time.
     *
     * @return Generator<int, string>
     *
     * @throws AssistantRefusedException
     */
    public function streamReply(Ticket $ticket, ?string $guidance = null): Generator;
}
