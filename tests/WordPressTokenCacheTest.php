<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\CachedToken;
use BrifnetMpesa\WordPress\WordPressTokenCache;
use PHPUnit\Framework\TestCase;

final class WordPressTokenCacheTest extends TestCase
{
    public function testTokenCanBeStoredAndRetrieved(): void
    {
        $store = new FakeTransientStore();

        $cache = new WordPressTokenCache(
            store: $store,
            cacheKey: 'brifnet_mpesa_access_token',
        );

        $cache->put(
            token: 'test-token',
            expiresAt: time() + 3600,
        );

        $cached = $cache->get();

        $this->assertInstanceOf(
            CachedToken::class,
            $cached
        );

        $this->assertSame(
            'test-token',
            $cached->token
        );
    }

    public function testMissingTokenReturnsNull(): void
    {
        $store = new FakeTransientStore();

        $cache = new WordPressTokenCache(
            store: $store,
            cacheKey: 'brifnet_mpesa_access_token',
        );

        $this->assertNull($cache->get());
    }

    public function testForgetRemovesToken(): void
    {
        $store = new FakeTransientStore();

        $cache = new WordPressTokenCache(
            store: $store,
            cacheKey: 'brifnet_mpesa_access_token',
        );

        $cache->put(
            token: 'test-token',
            expiresAt: time() + 3600,
        );

        $cache->forget();

        $this->assertNull($cache->get());

        $this->assertContains(
            'brifnet_mpesa_access_token',
            $store->deletedKeys
        );
    }

    public function testMalformedCachedValueReturnsNull(): void
    {
        $store = new FakeTransientStore();

        $store->values['brifnet_mpesa_access_token'] = [
            'invalid' => 'data',
        ];

        $cache = new WordPressTokenCache(
            store: $store,
            cacheKey: 'brifnet_mpesa_access_token',
        );

        $this->assertNull($cache->get());
    }
}
