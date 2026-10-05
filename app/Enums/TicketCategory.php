<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Bug = 'bug';
    case FeatureRequest = 'feature_request';
    case Account = 'account';
    case HowTo = 'how_to';
    case Other = 'other';
}
