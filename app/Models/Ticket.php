<?php

namespace App\Models;

use App\Ai\Data\TriageResult;
use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $customer_email
 * @property string $subject
 * @property string $body
 * @property TicketStatus $status
 * @property TicketCategory|null $category
 * @property TicketPriority|null $priority
 * @property Sentiment|null $sentiment
 * @property string|null $summary
 * @property list<string>|null $tags
 * @property Carbon|null $triaged_at
 * @property string|null $error
 * @property string|null $draft_reply
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'customer_email', 'subject', 'body', 'status', 'category', 'priority',
    'sentiment', 'summary', 'tags', 'triaged_at', 'error', 'draft_reply',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TicketStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'sentiment' => Sentiment::class,
            'tags' => 'array',
            'triaged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    | A ticket moves pending -> triaged, or pending -> failed -> pending (retry).
    */

    public function markTriaged(TriageResult $result): void
    {
        $this->update([
            ...$result->toAttributes(),
            'status' => TicketStatus::Triaged,
            'triaged_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => TicketStatus::Failed,
            'error' => $error,
        ]);
    }

    /**
     * Put the ticket back in the queue's waiting state before re-dispatching
     * TriageTicket. Earlier triage fields are kept until the new result lands.
     */
    public function markPending(): void
    {
        $this->update([
            'status' => TicketStatus::Pending,
            'error' => null,
        ]);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', TicketStatus::Pending);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->where('status', TicketStatus::Failed);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function urgent(Builder $query): void
    {
        $query->where('priority', TicketPriority::Urgent);
    }
}
