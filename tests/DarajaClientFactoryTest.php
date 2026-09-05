<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\DarajaConfig;
use BrifnetMpesa\Api\DarajaMpesaClient;
use BrifnetMpesa\WordPress\DarajaClientFactory;
use BrifnetMpesa\WordPress\WordPressHttpClient;
use PHPUnit\Framework\TestCase;

final class DarajaClientFactoryTest extends TestCase
{
    public function testFactoryCreatesDarajaMpesaClient(): void
    {
        $httpTransport = new FakeWordPressHttpTransport();

        $httpClient = new WordPressHttpClient(
            transport: $httpTransport,
        );

        $transientStore = new FakeTransientStore();

        $factory = new DarajaClientFactory(
            httpClient: $httpClient,
            transientStore: $transientStore,
        );

        $config = new DarajaConfig(
            consumerKey: 'test-consumer-key',
            consumerSecret: 'test-consumer-secret',
            businessShortCode: '174379',
            passkey: 'test-passkey',
            authUrl: 'https://example.com/oauth',
            stkPushUrl: 'https://example.com/stkpush',
            stkQueryUrl: 'https://example.com/stkpushquery',
            callbackUrl: 'https://example.com/callback',            
        );

        $client = $factory->create($config);

        $this->assertInstanceOf(
            DarajaMpesaClient::class,
            $client
        );
    }
}
