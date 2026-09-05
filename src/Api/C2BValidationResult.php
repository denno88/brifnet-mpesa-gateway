<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class C2BValidationResult
{
    public function __construct(
        public bool $accepted,
        public ?string $errorMessage = null,
    ) {
    }
}