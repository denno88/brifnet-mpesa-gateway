<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\WebhookDeliveryWorker;
use BrifnetMpesa\Domain\WebhookDelivery;
use BrifnetMpesa\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryWorkerTest extends TestCase
{
    public function test_it_delivers_pending_webhook(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        $updated = $repository->findByEventIdAndUrl(
            'EVENT-123',
            'https://example.com/webhook'
        );

        self::assertNotNull($updated);

        self::assertSame(
            'delivered',
            $updated->status
        );

        self::assertSame(
            'https://example.com/webhook',
            $httpClient->url
        );

        self::assertSame(
            '{"reference":"PAY-123"}',
            $httpClient->payload
        );
    }

    public function test_it_delivers_all_pending_webhooks(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $repository->save(
            new WebhookDelivery(
                eventId: 'EVENT-1',
                url: 'https://example.com/webhook-1',
                payload: '{"reference":"PAY-1"}',
            )
        );

        $repository->save(
            new WebhookDelivery(
                eventId: 'EVENT-2',
                url: 'https://example.com/webhook-2',
                payload: '{"reference":"PAY-2"}',
            )
        );

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        $first = $repository->findByEventIdAndUrl(
            'EVENT-1',
            'https://example.com/webhook-1'
        );

        $second = $repository->findByEventIdAndUrl(
            'EVENT-2',
            'https://example.com/webhook-2'
        );

        self::assertNotNull($first);
        self::assertNotNull($second);

        self::assertSame('delivered', $first->status);
        self::assertSame('delivered', $second->status);
    }

    public function test_failed_webhook_is_not_marked_as_delivered(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        $httpClient = new FakeWebhookHttpClient();
        $httpClient->shouldFail = true;

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        $updated = $repository->findByEventIdAndUrl(
            'EVENT-123',
            'https://example.com/webhook'
        );

        self::assertNotNull($updated);
        self::assertSame('pending', $updated->status);
        self::assertSame(1, $updated->attempts);
    }

    public function test_failed_webhook_increments_attempts(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $repository->save(
            new WebhookDelivery(
                eventId: 'EVENT-123',
                url: 'https://example.com/webhook',
                payload: '{"reference":"PAY-123"}',
            )
        );

        $httpClient = new FakeWebhookHttpClient();
        $httpClient->shouldFail = true;

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        $updated = $repository->findByEventIdAndUrl(
            'EVENT-123',
            'https://example.com/webhook'
        );

        self::assertNotNull($updated);
        self::assertSame('pending', $updated->status);
        self::assertSame(1, $updated->attempts);
    }

    public function test_it_does_not_deliver_webhook_that_has_exhausted_retries(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
            attempts: 3,
        );

        $repository->save($delivery);

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        self::assertNull($httpClient->url);
        self::assertNull($httpClient->payload);
    }

    public function test_it_marks_exhausted_webhook_as_permanently_failed(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
            attempts: 3,
        );

        $repository->save($delivery);

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            $repository,
            $httpClient,
            new WebhookSignature(),
            'test-secret',
        );

        $worker->run();

        $updated = $repository->findByEventIdAndUrl(
            'EVENT-123',
            'https://example.com/webhook'
        );

        self::assertNotNull($updated);
        self::assertSame('failed', $updated->status);
        self::assertSame(3, $updated->attempts);
        self::assertNull($httpClient->url);
    }

    public function test_it_sends_a_timestamp_and_signed_webhook(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $payload = '{"reference":"PAY-123"}';

        $repository->save(
            new WebhookDelivery(
                eventId: 'evt-123',
                url: 'https://example.com/webhook',
                payload: $payload,
            )
        );

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            repository: $repository,
            httpClient: $httpClient,
            signature: new WebhookSignature(),
            webhookSecret: 'test-secret',
        );

        $worker->run();

        self::assertArrayHasKey(
            'X-BrifNet-Timestamp',
            $httpClient->headers
        );

        self::assertArrayHasKey(
            'X-BrifNet-Signature',
            $httpClient->headers
        );

        $timestamp = $httpClient->headers['X-BrifNet-Timestamp'];

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $timestamp
        );

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            'test-secret',
        );

        self::assertSame(
            'sha256=' . $expectedSignature,
            $httpClient->headers['X-BrifNet-Signature']
        );
    }

    public function test_signature_is_not_generated_from_payload_alone(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $payload = '{"reference":"PAY-123"}';

        $repository->save(
            new WebhookDelivery(
                eventId: 'evt-123',
                url: 'https://example.com/webhook',
                payload: $payload,
            )
        );

        $httpClient = new FakeWebhookHttpClient();

        $worker = new WebhookDeliveryWorker(
            repository: $repository,
            httpClient: $httpClient,
            signature: new WebhookSignature(),
            webhookSecret: 'test-secret',
        );

        $worker->run();

        $timestamp = $httpClient->headers['X-BrifNet-Timestamp'];
        $actualSignature = $httpClient->headers['X-BrifNet-Signature'];

        $oldSignature = hash_hmac(
            'sha256',
            $payload,
            'test-secret',
        );

        self::assertNotSame(
            $oldSignature,
            $actualSignature
        );

        self::assertSame(
            'sha256=' . hash_hmac(
                'sha256',
                $timestamp . '.' . $payload,
                'test-secret',
            ),
            $actualSignature
        );
    }

}