<?php

namespace App\Enums;

enum LoanStatus: string
{
    case PENDING_FUNDING = 'pending_funding';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';
    case CANCELLED = 'cancelled';
}
