<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Api\AccessTokenProvider;
use BrifnetMpesa\Api\CachedAccessTokenProvider;
use BrifnetMpesa\Api\Clock;
use BrifnetMpesa\Api\DarajaAuthClient;
use BrifnetMpesa\Api\DarajaConfig;
use BrifnetMpesa\Api\DarajaMpesaClient;
use BrifnetMpesa\Api\MpesaClient;
use BrifnetMpesa\Api\StkPasswordGenerator;
use BrifnetMpesa\Api\StkPushRequestBuilder;
use BrifnetMpesa\Api\SystemClock;
use BrifnetMpesa\Api\SystemUnixClock;

final class DarajaClientFactory
{
    public function __construct(
        private readonly WordPressHttpClient $httpClient,
        private readonly TransientStore $transientStore,
    ) {
    }

    public function create(
        DarajaConfig $config,
    ): MpesaClient {
        $authClient = new DarajaAuthClient(
            httpClient: $this->httpClient,
            consumerKey: $config->consumerKey,
            consumerSecret: $config->consumerSecret,
            authUrl: $config->authUrl,
        );

        $tokenCache = new WordPressTokenCache(
            store: $this->transientStore,
            cacheKey: 'brifnet_mpesa_access_token',
        );

        $tokenProvider = $this->createTokenProvider(
            authClient: $authClient,
            tokenCache: $tokenCache,
        );

        return new DarajaMpesaClient(
            httpClient: $this->httpClient,
            accessTokenProvider: $tokenProvider,
            clock: new SystemClock(),
            passwordGenerator: new StkPasswordGenerator(),
            requestBuilder: new StkPushRequestBuilder(
                businessShortCode: $config->businessShortCode,
                transactionType: $config->transactionType,
                callbackUrl: $config->callbackUrl,
                transactionDesc: $config->transactionDescription,
            ),
            businessShortCode: $config->businessShortCode,
            passkey: $config->passkey,
            stkPushUrl: $config->stkPushUrl,
            stkQueryUrl: $config->stkQueryUrl,
        );
    }

    private function createTokenProvider(
        DarajaAuthClient $authClient,
        WordPressTokenCache $tokenCache,
    ): AccessTokenProvider {
        return new CachedAccessTokenProvider(
            authClient: $authClient,
            cache: $tokenCache,
            clock: new SystemUnixClock(),
        );
    }
}
