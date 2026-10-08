<?php

namespace App\Enums;

/**
 * Mirrored by a union type in resources/js/types/tickets.ts; change both together.
 */
enum TicketStatus: string
{
    case Pending = 'pending';
    case Triaged = 'triaged';
    case Failed = 'failed';
}
