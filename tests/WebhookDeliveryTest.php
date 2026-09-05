<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookDelivery;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryTest extends TestCase
{
    public function test_new_delivery_is_pending_with_zero_attempts(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        self::assertSame('event-123', $delivery->eventId);
        self::assertSame(
            'https://example.com/webhook',
            $delivery->url
        );
        self::assertSame(
            '{"reference":"PAY-123"}',
            $delivery->payload
        );
        self::assertSame('pending', $delivery->status);
        self::assertSame(0, $delivery->attempts);
    }

    public function test_delivery_can_be_marked_as_delivered(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $delivered = $delivery->markDelivered();

        self::assertSame('delivered', $delivered->status);
    }

    public function test_marking_delivery_as_delivered_does_not_mutate_original(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $delivered = $delivery->markDelivered();

        self::assertSame('pending', $delivery->status);
        self::assertSame('delivered', $delivered->status);
    }

    public function test_failed_delivery_increments_attempts_and_remains_pending(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $failed = $delivery->markFailed();

        self::assertSame('pending', $failed->status);
        self::assertSame(1, $failed->attempts);

        self::assertSame(0, $delivery->attempts);
    }

    public function test_delivery_can_report_that_it_is_retryable(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
            attempts: 2,
        );

        self::assertTrue(
            $delivery->isRetryable(3)
        );

        self::assertFalse(
            $delivery->isRetryable(2)
        );
    }

    public function test_delivery_is_not_retryable_after_max_attempts(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
            attempts: 3,
        );

        self::assertFalse(
            $delivery->isRetryable(3)
        );
    }

    public function test_delivery_can_be_marked_as_permanently_failed(): void
    {
        $delivery = new WebhookDelivery(
            eventId: 'EVENT-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
            attempts: 3,
        );

        $failed = $delivery->markPermanentlyFailed();

        self::assertSame('failed', $failed->status);
        self::assertSame(3, $failed->attempts);
    }
}