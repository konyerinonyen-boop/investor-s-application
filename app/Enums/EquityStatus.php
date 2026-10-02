<?php

namespace App\Enums;

enum EquityStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
