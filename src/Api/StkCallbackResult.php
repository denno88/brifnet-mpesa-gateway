<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class StkCallbackResult
{
    public function __construct(
        public string $merchantRequestId,
        public string $checkoutRequestId,
        public int $resultCode,
        public string $resultDescription,
        public ?int $amount,
        public ?string $receipt,
        public ?string $phone,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->resultCode === 0;
    }
}
