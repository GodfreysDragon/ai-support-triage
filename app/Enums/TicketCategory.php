<?php

namespace App\Enums;

/**
 * Mirrored by a union type in resources/js/types/tickets.ts; change both together.
 */
enum TicketCategory: string
{
    case Billing = 'billing';
    case Bug = 'bug';
    case FeatureRequest = 'feature_request';
    case Account = 'account';
    case HowTo = 'how_to';
    case Other = 'other';
}
