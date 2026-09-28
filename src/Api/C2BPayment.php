<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class C2BPayment
{
    public function __construct(
        public string $transactionId,
        public string $phone,
        public int $amount,
        public string $accountNumber,
        public string $transactionTime,
        public string $businessShortCode,
    ) {
    }
}