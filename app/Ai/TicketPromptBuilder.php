<?php

namespace App\Ai;

use App\Models\Ticket;
use Illuminate\Support\Facades\File;

/**
 * Builds the prompts sent to the model. System prompts live in
 * resources/prompts/ so wording changes show up as plain-text diffs.
 *
 * Ticket text is always wrapped in <ticket> tags so the system prompts can
 * mark everything inside as untrusted customer input (a prompt-injection
 * boundary), separate from our own instructions.
 */
class TicketPromptBuilder
{
    public function triageSystem(): string
    {
        return $this->load('triage-system');
    }

    public function replySystem(): string
    {
        return $this->load('reply-system');
    }

    /**
     * The user message for triage: just the ticket.
     */
    public function triage(Ticket $ticket): string
    {
        return $this->ticket($ticket);
    }

    /**
     * The user message for drafting a reply. The triage result, when there is
     * one, is included so the draft matches the ticket's priority and the
     * customer's mood; agent guidance steers tone and content.
     */
    public function reply(Ticket $ticket, ?string $guidance = null): string
    {
        $prompt = $this->ticket($ticket);

        if ($ticket->summary) {
            $prompt .= "\n\n<triage>{$ticket->category?->value}, {$ticket->priority?->value} priority, customer sentiment {$ticket->sentiment?->value}. {$ticket->summary}</triage>";
        }

        if (filled($guidance)) {
            $prompt .= "\n\n<agent_guidance>{$guidance}</agent_guidance>\nFollow the agent's guidance when drafting.";
        }

        return $prompt;
    }

    private function ticket(Ticket $ticket): string
    {
        $from = $ticket->customer_email ?? 'unknown';

        return "<ticket>\nFrom: {$from}\nSubject: {$ticket->subject}\n\n{$ticket->body}\n</ticket>";
    }

    private function load(string $name): string
    {
        return rtrim(File::get(resource_path("prompts/{$name}.md")));
    }
}
