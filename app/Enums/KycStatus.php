<?php

namespace App\Enums;

enum KycStatus: string
{
    case NOT_STARTED = 'not_started';
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
