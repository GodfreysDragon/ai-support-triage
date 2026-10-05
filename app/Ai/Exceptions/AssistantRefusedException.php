<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * The model declined the request (stop_reason "refusal") and no fallback
 * model was able to complete it. Retrying the same input won't help.
 */
class AssistantRefusedException extends RuntimeException
{
    public static function withCategory(?string $category): self
    {
        return new self($category
            ? "The AI assistant declined this request (category: {$category})."
            : 'The AI assistant declined this request.');
    }
}
