<?php

namespace App\Ai\Data;

use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Support\Arr;

final readonly class TriageResult
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public TicketCategory $category,
        public TicketPriority $priority,
        public Sentiment $sentiment,
        public string $summary,
        public array $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            category: TicketCategory::from($data['category']),
            priority: TicketPriority::from($data['priority']),
            sentiment: Sentiment::from($data['sentiment']),
            summary: (string) $data['summary'],
            tags: array_values(array_map('strval', Arr::wrap($data['tags'] ?? []))),
        );
    }

    /**
     * JSON schema handed to the model as a structured-output format, so the
     * response is guaranteed to decode into this shape.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $values = fn (string $enum): array => array_column($enum::cases(), 'value');

        return [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'enum' => $values(TicketCategory::class)],
                'priority' => [
                    'type' => 'string',
                    'enum' => $values(TicketPriority::class),
                    'description' => TicketPriority::definitions(),
                ],
                'sentiment' => ['type' => 'string', 'enum' => $values(Sentiment::class)],
                'summary' => ['type' => 'string', 'description' => 'One sentence an agent can scan in a queue.'],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Up to 5 short lowercase tags (product area, platform, feature names).',
                ],
            ],
            'required' => ['category', 'priority', 'sentiment', 'summary', 'tags'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'category' => $this->category,
            'priority' => $this->priority,
            'sentiment' => $this->sentiment,
            'summary' => $this->summary,
            'tags' => array_slice($this->tags, 0, 5),
        ];
    }
}
