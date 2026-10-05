<?php

namespace App\Models;

use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
        'status' => 'pending',
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
}
