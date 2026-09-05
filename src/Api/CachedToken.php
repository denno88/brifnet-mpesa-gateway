<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class CachedToken
{
    public function __construct(
        public string $token,
        public int $expiresAt,
    ) {
    }
}