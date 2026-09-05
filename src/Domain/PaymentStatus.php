<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
