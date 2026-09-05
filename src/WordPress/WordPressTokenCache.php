<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Api\CachedToken;
use BrifnetMpesa\Api\TokenCache;

final class WordPressTokenCache implements TokenCache
{
    public function __construct(
        private readonly TransientStore $store,
        private readonly string $cacheKey,
    ) {
    }

    public function get(): ?CachedToken
    {
        $cached = $this->store->get($this->cacheKey);

        if (!is_array($cached)) {
            return null;
        }

        if (
            !isset($cached['token']) ||
            !isset($cached['expires_at']) ||
            !is_string($cached['token']) ||
            !is_int($cached['expires_at'])
        ) {
            return null;
        }

        return new CachedToken(
            token: $cached['token'],
            expiresAt: $cached['expires_at'],
        );
    }

    public function put(
        string $token,
        int $expiresAt,
    ): void {
        $ttl = max(
            1,
            $expiresAt - time()
        );

        $this->store->set(
            $this->cacheKey,
            [
                'token' => $token,
                'expires_at' => $expiresAt,
            ],
            $ttl
        );
    }

    public function forget(): void
    {
        $this->store->delete($this->cacheKey);
    }
}
