<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookEndpoint;
use BrifnetMpesa\WordPress\WordPressWebhookEndpointRepository;
use PHPUnit\Framework\TestCase;

final class WordPressWebhookEndpointRepositoryTest extends TestCase
{
    public function testEndpointCanBeSaved(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: ['payment.completed']
        );

        $repository->save($endpoint);

        $found = $repository->findByUrl(
            'https://example.com/webhook'
        );

        $this->assertNotNull($found);

        $this->assertSame(
            $endpoint->url,
            $found->url
        );

        $this->assertSame(
            $endpoint->active,
            $found->active
        );

        $this->assertSame(
            $endpoint->events,
            $found->events
        );
    }

    public function testEndpointCanBeUpdated(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: ['payment.completed']
        );

        $repository->save($endpoint);

        $deactivated = $endpoint->deactivate();

        $repository->update($deactivated);

        $found = $repository->findByUrl(
            'https://example.com/webhook'
        );

        $this->assertNotNull($found);

        $this->assertFalse($found->active);
    }

    public function testSaveThrowsWhenDatabaseInsertFails(): void
    {
        $database = new FakeDatabase();
        $database->insertShouldFail = true;

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: ['payment.completed']
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to save webhook endpoint.'
        );

        $repository->save($endpoint);
    }

    public function testUpdateThrowsWhenDatabaseUpdateFails(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: ['payment.completed']
        );

        $database->updateShouldFail = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to update webhook endpoint.'
        );

        $repository->update($endpoint);
    }

    public function testFindByUrlReturnsNullWhenEndpointDoesNotExist(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $this->assertNull(
            $repository->findByUrl(
                'https://example.com/missing'
            )
        );
    }

    public function testFindActiveForEventReturnsMatchingActiveEndpoints(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressWebhookEndpointRepository(
            $database,
            'wp_brifnet_webhook_endpoints'
        );

        $matching = new WebhookEndpoint(
            url: 'https://example.com/payment',
            events: ['payment.completed']
        );

        $otherEvent = new WebhookEndpoint(
            url: 'https://example.com/other',
            events: ['payment.failed']
        );

        $inactive = new WebhookEndpoint(
            url: 'https://example.com/inactive',
            active: false,
            events: ['payment.completed']
        );

        $repository->save($matching);
        $repository->save($otherEvent);
        $repository->save($inactive);

        $result = $repository->findActiveForEvent(
            'payment.completed'
        );

        $this->assertCount(1, $result);

        $this->assertSame(
            $matching->url,
            $result[0]->url
        );
    }
}