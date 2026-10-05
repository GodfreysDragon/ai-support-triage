<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Triaged = 'triaged';
    case Failed = 'failed';
}
