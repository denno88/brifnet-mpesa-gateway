<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class ReactivateWebhookEndpointTest extends TestCase
{
    public function testEndpointCanBeReactivated(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook',
            active: false,
            events: ['payment.completed']
        );

        $repository->save($endpoint);

        $reactivate = new ReactivateWebhookEndpoint(
            $repository
        );

        $updated = $reactivate->execute($endpoint->url);

        $this->assertTrue($updated->active);

        $this->assertSame(
            $updated,
            $repository->findByUrl($endpoint->url)
        );
    }

    public function testUnknownEndpointCannotBeReactivated(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $reactivate = new ReactivateWebhookEndpoint(
            $repository
        );

        $this->expectException(\InvalidArgumentException::class);

        $reactivate->execute(
            'https://unknown.example.com/webhook'
        );
    }
}