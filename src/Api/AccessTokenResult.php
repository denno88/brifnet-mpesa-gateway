<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class AccessTokenResult
{
    public function __construct(
        public bool $successful,
        public ?string $accessToken,
        public ?int $expiresIn = null,
        public ?string $error = null,
    ) {
    }
}
