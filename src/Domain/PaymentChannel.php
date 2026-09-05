<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

enum PaymentChannel: string
{
    case STK = 'STK';
    case C2B = 'C2B';
}
