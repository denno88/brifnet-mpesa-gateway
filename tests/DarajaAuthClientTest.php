<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\AccessTokenResult;
use BrifnetMpesa\Api\DarajaAuthClient;
use BrifnetMpesa\Api\HttpResponse;
use PHPUnit\Framework\TestCase;

final class DarajaAuthClientTest extends TestCase
{
    private function createClient(
        FakeHttpClient $httpClient,
    ): DarajaAuthClient {
        return new DarajaAuthClient(
            httpClient: $httpClient,
            consumerKey: 'test-consumer-key',
            consumerSecret: 'test-consumer-secret',
            authUrl: 'https://example.com/oauth/v1/generate',
        );
    }

    public function testSuccessfulAuthenticationReturnsAccessToken(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => '3599',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient($httpClient);

        $result = $client->authenticate();

        $this->assertInstanceOf(
            AccessTokenResult::class,
            $result
        );

        $this->assertTrue($result->successful);

        $this->assertSame(
            'test-access-token',
            $result->accessToken
        );

        $this->assertSame(
            3599,
            $result->expiresIn
        );

        $this->assertNull($result->error);
    }

    public function testAuthenticationBuildsCorrectHttpRequest(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => '3599',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient($httpClient);

        $client->authenticate();

        $this->assertSame(
            1,
            $httpClient->callCount
        );

        $this->assertSame(
            'GET',
            $httpClient->lastMethod
        );

        $this->assertSame(
            'https://example.com/oauth/v1/generate?grant_type=client_credentials',
            $httpClient->url
        );

        $this->assertSame(
            [
                'Authorization' => 'Basic ' . base64_encode(
                    'test-consumer-key:test-consumer-secret'
                ),
            ],
            $httpClient->headers
        );
    }

    public function testProviderAuthenticationErrorIsReturned(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 401,
            body: json_encode([
                'error' => 'invalid_client',
                'error_description' => 'Invalid credentials.',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient($httpClient);

        $result = $client->authenticate();

        $this->assertFalse($result->successful);

        $this->assertNull($result->accessToken);

        $this->assertSame(
            'Invalid credentials.',
            $result->error
        );
    }

    public function testSuccessfulResponseWithoutTokenIsRejected(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'expires_in' => '3599',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient($httpClient);

        $result = $client->authenticate();

        $this->assertFalse($result->successful);

        $this->assertNull($result->accessToken);

        $this->assertSame(
            'Authentication response did not contain an access token.',
            $result->error
        );
    }
}
