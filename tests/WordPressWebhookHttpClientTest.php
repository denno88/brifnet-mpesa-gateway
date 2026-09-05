<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressWebhookHttpClient;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Api\HttpResponse;

final class WordPressWebhookHttpClientTest extends TestCase
{
    public function test_it_sends_post_request_with_json_payload(): void
    {
        $http = new FakeHttpClient();

        $client = new WordPressWebhookHttpClient($http);

        $client->send(
            'https://example.com/webhook',
            '{"reference":"PAY-123"}',
        );

        self::assertSame(
            'https://example.com/webhook',
            $http->url
        );

        self::assertSame(
            'application/json',
            $http->headers['Content-Type']
        );

        self::assertSame(
            '{"reference":"PAY-123"}',
            $http->body
        );
    }

    public function test_it_throws_when_webhook_returns_non_success_status(): void
    {
        $http = new FakeHttpClient();

        $http->response = new HttpResponse(
            statusCode: 500,
            body: 'server error',
        );

        $client = new WordPressWebhookHttpClient($http);

        $this->expectException(\RuntimeException::class);

        $client->send(
            'https://example.com/webhook',
            '{"reference":"PAY-123"}',
        );
    }
}