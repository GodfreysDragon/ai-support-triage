<?php

namespace App\Enums;

/**
 * Mirrored by a union type in resources/js/types/tickets.ts; change both together.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * What each priority means. The model is given these definitions in the
     * triage schema, so they are also the rules it classifies by.
     */
    public function description(): string
    {
        return match ($this) {
            self::Urgent => 'outage, data loss, security or payment failure blocking the customer',
            self::High => 'core feature broken',
            self::Medium => 'degraded or confusing',
            self::Low => 'questions and suggestions',
        };
    }

    /**
     * All definitions as one line, most urgent first, e.g.
     * "urgent = ...; high = ...; medium = ...; low = ...".
     */
    public static function definitions(): string
    {
        $lines = array_map(
            fn (self $priority): string => "{$priority->value} = {$priority->description()}",
            array_reverse(self::cases()),
        );

        return implode('; ', $lines).'.';
    }
}
