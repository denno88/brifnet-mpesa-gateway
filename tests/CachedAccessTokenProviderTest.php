<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\AccessTokenResult;
use BrifnetMpesa\Api\CachedAccessTokenProvider;
use PHPUnit\Framework\TestCase;

final class CachedAccessTokenProviderTest extends TestCase
{
    public function testMissingCachedTokenAuthenticatesAndCachesNewToken(): void
    {
        $authenticator = new FakeAccessTokenAuthenticator();

        $authenticator->result = new AccessTokenResult(
            successful: true,
            accessToken: 'new-access-token',
            expiresIn: 3599,
        );

        $cache = new FakeTokenCache();

        $clock = new FakeUnixClock(1_000_000);

        $provider = new CachedAccessTokenProvider(
            authClient: $authenticator,
            cache: $cache,
            clock: $clock,
        );

        $result = $provider->getAccessToken();

        $this->assertTrue($result->successful);

        $this->assertSame(
            'new-access-token',
            $result->accessToken
        );

        $this->assertSame(
            1,
            $authenticator->callCount
        );

        $this->assertSame(
            1,
            $cache->putCount
        );

        $this->assertSame(
            'new-access-token',
            $cache->cachedToken?->token
        );

        $this->assertSame(
            1_003_599,
            $cache->cachedToken?->expiresAt
        );
    }

    public function testValidCachedTokenIsReturnedWithoutAuthentication(): void
    {
        $authenticator = new FakeAccessTokenAuthenticator();

        $authenticator->result = new AccessTokenResult(
            successful: true,
            accessToken: 'should-not-be-used',
            expiresIn: 3599,
        );

        $cache = new FakeTokenCache();

        $cache->put(
            token: 'cached-access-token',
            expiresAt: 1_003_599,
        );

        $clock = new FakeUnixClock(1_000_000);

        $provider = new CachedAccessTokenProvider(
            authClient: $authenticator,
            cache: $cache,
            clock: $clock,
        );

        $result = $provider->getAccessToken();

        $this->assertTrue($result->successful);

        $this->assertSame(
            'cached-access-token',
            $result->accessToken
        );

        $this->assertSame(
            0,
            $authenticator->callCount
        );

        $this->assertSame(
            1,
            $cache->putCount
        );
    }

    public function testExpiredCachedTokenIsForgottenAndReplaced(): void
    {
        $authenticator = new FakeAccessTokenAuthenticator();

        $authenticator->result = new AccessTokenResult(
            successful: true,
            accessToken: 'replacement-token',
            expiresIn: 3599,
        );

        $cache = new FakeTokenCache();

        $cache->put(
            token: 'expired-token',
            expiresAt: 999_999,
        );

        $clock = new FakeUnixClock(1_000_000);

        $provider = new CachedAccessTokenProvider(
            authClient: $authenticator,
            cache: $cache,
            clock: $clock,
        );

        $result = $provider->getAccessToken();

        $this->assertTrue($result->successful);

        $this->assertSame(
            'replacement-token',
            $result->accessToken
        );

        $this->assertSame(
            1,
            $authenticator->callCount
        );

        $this->assertSame(
            1,
            $cache->forgetCount
        );

        $this->assertSame(
            2,
            $cache->putCount
        );

        $this->assertSame(
            'replacement-token',
            $cache->cachedToken?->token
        );

        $this->assertSame(
            1_003_599,
            $cache->cachedToken?->expiresAt
        );
    }

    public function testAuthenticationFailureDoesNotCacheToken(): void
    {
        $authenticator = new FakeAccessTokenAuthenticator();

        $authenticator->result = new AccessTokenResult(
            successful: false,
            accessToken: null,
            error: 'Invalid credentials.',
        );

        $cache = new FakeTokenCache();

        $clock = new FakeUnixClock(1_000_000);

        $provider = new CachedAccessTokenProvider(
            authClient: $authenticator,
            cache: $cache,
            clock: $clock,
        );

        $result = $provider->getAccessToken();

        $this->assertFalse($result->successful);

        $this->assertNull($result->accessToken);

        $this->assertSame(
            'Invalid credentials.',
            $result->error
        );

        $this->assertSame(
            1,
            $authenticator->callCount
        );

        $this->assertSame(
            0,
            $cache->putCount
        );

        $this->assertNull($cache->cachedToken);
    }
}
