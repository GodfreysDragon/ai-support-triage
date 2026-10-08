<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ticket as the frontend sees it (the Ticket type in
 * resources/js/types/tickets.ts). Fields are listed explicitly so new
 * columns aren't sent to the browser by accident.
 *
 * Pages call ->resolve() so the props aren't wrapped in a "data" key.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_email' => $this->customer_email,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'category' => $this->category,
            'priority' => $this->priority,
            'sentiment' => $this->sentiment,
            'summary' => $this->summary,
            'tags' => $this->tags ?? [],
            'error' => $this->error,
            'draft_reply' => $this->draft_reply,
            'triaged_at' => $this->triaged_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
