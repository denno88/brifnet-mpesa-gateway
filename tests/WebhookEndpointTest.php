<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class WebhookEndpointTest extends TestCase
{
    public function testEndpointStoresUrl(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook'
        );

        $this->assertSame(
            'https://example.com/webhook',
            $endpoint->url
        );
    }

    public function testEndpointIsActiveByDefault(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook'
        );

        $this->assertTrue($endpoint->active);
    }

    public function testEndpointStoresSubscribedEvents(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: [
                'payment.completed',
                'payment.failed',
            ]
        );

        $this->assertSame(
            [
                'payment.completed',
                'payment.failed',
            ],
            $endpoint->events
        );
    }

    public function testEndpointAcceptsSubscribedEvent(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: [
                'payment.completed',
                'payment.failed',
            ]
        );

        $this->assertTrue(
            $endpoint->acceptsEvent('payment.completed')
        );

        $this->assertFalse(
            $endpoint->acceptsEvent('payment.refunded')
        );
    }

    public function testEndpointRejectsEmptyUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookEndpoint(
            url: ''
        );
    }

    public function testEndpointRejectsInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookEndpoint(
            url: 'not-a-url'
        );
    }

    public function testEndpointRejectsEmptyEventName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookEndpoint(
            url: 'https://example.com/webhook',
            events: ['payment.completed', '']
        );
    }

    public function testEndpointCanBeDeactivated(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook'
        );

        $deactivated = $endpoint->deactivate();

        $this->assertFalse($deactivated->active);
        $this->assertTrue($endpoint->active);
    }

    public function testEndpointCanBeActivated(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            active: false
        );

        $activated = $endpoint->activate();

        $this->assertTrue($activated->active);
        $this->assertFalse($endpoint->active);
    }

    public function testInactiveEndpointDoesNotAcceptEvents(): void
    {
        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            active: false,
            events: ['payment.completed']
        );

        $this->assertFalse(
            $endpoint->acceptsEvent('payment.completed')
        );
    }
}