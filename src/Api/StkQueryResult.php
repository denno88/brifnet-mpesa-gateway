<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class StkQueryResult
{
    public function __construct(
        public bool $successful,
        public ?string $responseCode,
        public ?string $responseDescription,
        public ?string $merchantRequestId,
        public ?string $checkoutRequestId,
        public ?string $resultCode,
        public ?string $resultDescription,
    ) {
    }
}