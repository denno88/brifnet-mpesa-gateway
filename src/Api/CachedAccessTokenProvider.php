<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class CachedAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private readonly AccessTokenAuthenticator $authClient,
        private readonly TokenCache $cache,
        private readonly UnixClock $clock,
    ) {
    }

    public function getAccessToken(): AccessTokenResult
    {
        $cachedToken = $this->cache->get();

        if (
            $cachedToken !== null &&
            $cachedToken->expiresAt > $this->clock->now()
        ) {
            return new AccessTokenResult(
                successful: true,
                accessToken: $cachedToken->token,
            );
        }

        if ($cachedToken !== null) {
            $this->cache->forget();
        }

        $result = $this->authClient->authenticate();

        if (
            !$result->successful ||
            $result->accessToken === null
        ) {
            return $result;
        }

        if ($result->expiresIn !== null) {
            $expiresAt = $this->clock->now() + $result->expiresIn;

            $this->cache->put(
                token: $result->accessToken,
                expiresAt: $expiresAt,
            );
        }

        return $result;
    }
}
