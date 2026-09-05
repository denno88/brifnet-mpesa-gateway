<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class StkPushResult
{
    public function __construct(
        public bool $accepted,
        public ?string $merchantRequestId,
        public ?string $checkoutRequestId,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }
}
