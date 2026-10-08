<?php

namespace App\Enums;

/**
 * Mirrored by a union type in resources/js/types/tickets.ts; change both together.
 */
enum Sentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
    case Angry = 'angry';
}
