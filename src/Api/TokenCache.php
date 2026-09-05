<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface TokenCache
{
    public function get(): ?CachedToken;

    public function put(
        string $token,
        int $expiresAt,
    ): void;

    public function forget(): void;
}
