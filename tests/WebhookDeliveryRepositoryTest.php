<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookDelivery;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryRepositoryTest extends TestCase
{
    public function test_delivery_can_be_saved_and_found_by_event_id_and_url(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        self::assertSame(
            $delivery,
            $repository->findByEventIdAndUrl(
                'event-123',
                'https://example.com/webhook'
            )
        );
    }

    public function test_unknown_delivery_returns_null(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        self::assertNull(
            $repository->findByEventIdAndUrl(
                'missing-event',
                'https://example.com/webhook'
            )
        );
    }

    public function test_pending_deliveries_can_be_found(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $pending = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://pending.example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $delivered = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://delivered.example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $delivered = $delivered->markDelivered();

        $repository->save($pending);
        $repository->save($delivered);

        $pendingDeliveries = $repository->findPending();

        self::assertCount(1, $pendingDeliveries);
        self::assertSame($pending, $pendingDeliveries[0]);
    }

    public function test_delivery_can_be_updated(): void
    {
        $repository = new FakeWebhookDeliveryRepository();

        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        $delivered = $delivery->markDelivered();

        $repository->update($delivered);

        self::assertSame(
            'delivered',
            $repository->findByEventIdAndUrl(
                'event-123',
                'https://example.com/webhook'
            )?->status
        );
    }
}