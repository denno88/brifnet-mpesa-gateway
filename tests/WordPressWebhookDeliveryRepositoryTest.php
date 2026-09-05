<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookDelivery;
use BrifnetMpesa\WordPress\WordPressWebhookDeliveryRepository;
use PHPUnit\Framework\TestCase;

final class WordPressWebhookDeliveryRepositoryTest extends TestCase
{
    public function test_delivery_can_be_saved(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookDeliveryRepository(
            database: $database,
            tableName: 'wp_webhook_deliveries',
        );

        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        self::assertCount(1, $database->inserted);

        self::assertSame(
            'wp_webhook_deliveries',
            $database->inserted[0]['table']
        );

        self::assertSame(
            'event-123',
            $database->inserted[0]['data']['event_id']
        );

        self::assertSame(
            'https://example.com/webhook',
            $database->inserted[0]['data']['url']
        );

        self::assertSame(
            '{"reference":"PAY-123"}',
            $database->inserted[0]['data']['payload']
        );

        self::assertSame(
            'pending',
            $database->inserted[0]['data']['status']
        );

        self::assertSame(
            0,
            $database->inserted[0]['data']['attempts']
        );
    }

    public function test_delivery_can_be_found_by_event_id_and_url(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookDeliveryRepository(
            database: $database,
            tableName: 'wp_webhook_deliveries',
        );

        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        $found = $repository->findByEventIdAndUrl(
            'event-123',
            'https://example.com/webhook',
        );

        self::assertNotNull($found);
        self::assertSame('event-123', $found->eventId);
        self::assertSame(
            'https://example.com/webhook',
            $found->url
        );
        self::assertSame(
            '{"reference":"PAY-123"}',
            $found->payload
        );
        self::assertSame('pending', $found->status);
        self::assertSame(0, $found->attempts);
    }

    public function test_unknown_delivery_returns_null(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookDeliveryRepository(
            database: $database,
            tableName: 'wp_webhook_deliveries',
        );

        $found = $repository->findByEventIdAndUrl(
            'unknown-event',
            'https://example.com/webhook',
        );

        self::assertNull($found);
    }

    public function test_pending_deliveries_can_be_found(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookDeliveryRepository(
            database: $database,
            tableName: 'wp_webhook_deliveries',
        );

        $pending = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $delivered = new WebhookDelivery(
            eventId: 'event-456',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-456"}',
            status: 'delivered',
        );

        $repository->save($pending);
        $repository->save($delivered);

        $found = $repository->findPending();

        self::assertCount(1, $found);
        self::assertSame('event-123', $found[0]->eventId);
        self::assertSame(
            'https://example.com/webhook',
            $found[0]->url
        );
        self::assertSame('pending', $found[0]->status);
    }

    public function test_delivery_can_be_updated(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookDeliveryRepository(
            database: $database,
            tableName: 'wp_webhook_deliveries',
        );

        $delivery = new WebhookDelivery(
            eventId: 'event-123',
            url: 'https://example.com/webhook',
            payload: '{"reference":"PAY-123"}',
        );

        $repository->save($delivery);

        $updated = $delivery->markDelivered();

        $repository->update($updated);

        self::assertCount(1, $database->updated);

        self::assertSame(
            'wp_webhook_deliveries',
            $database->updated[0]['table']
        );

        self::assertSame(
            'delivered',
            $database->updated[0]['data']['status']
        );

        self::assertSame(
            0,
            $database->updated[0]['data']['attempts']
        );

        self::assertSame(
            'event-123',
            $database->updated[0]['where']['event_id']
        );

        self::assertSame(
            'https://example.com/webhook',
            $database->updated[0]['where']['url']
        );
    }
}