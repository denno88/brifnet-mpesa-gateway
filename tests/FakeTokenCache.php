<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\CachedToken;
use BrifnetMpesa\Api\TokenCache;

final class FakeTokenCache implements TokenCache
{
    public ?CachedToken $cachedToken = null;

    public int $putCount = 0;

    public int $forgetCount = 0;

    public function get(): ?CachedToken
    {
        return $this->cachedToken;
    }

    public function put(
        string $token,
        int $expiresAt,
    ): void {
        $this->cachedToken = new CachedToken(
            token: $token,
            expiresAt: $expiresAt,
        );

        $this->putCount++;
    }

    public function forget(): void
    {
        $this->cachedToken = null;
        $this->forgetCount++;
    }
}
