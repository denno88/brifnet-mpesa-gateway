<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookHttpClient;
use PHPUnit\Framework\TestCase;

final class WebhookHttpClientTest extends TestCase
{
    public function test_webhook_http_client_can_send_payload(): void
    {
        $client = new FakeWebhookHttpClient();

        $client->send(
            'https://example.com/webhook',
            '{"reference":"PAY-123"}',
        );

        self::assertSame(
            'https://example.com/webhook',
            $client->url
        );

        self::assertSame(
            '{"reference":"PAY-123"}',
            $client->payload
        );
    }

    public function test_webhook_http_client_can_send_payload_with_headers(): void
    {
        $client = new FakeWebhookHttpClient();

        $client->send(
            'https://example.com/webhook',
            '{"reference":"PAY-123"}',
            [
                'X-Webhook-Signature' => 'abc123',
            ],
        );

        self::assertSame(
            'abc123',
            $client->headers['X-Webhook-Signature']
        );
    }
}