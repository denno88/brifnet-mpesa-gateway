<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class DeactivateWebhookEndpointTest extends TestCase
{
    public function testEndpointCanBeDeactivated(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $endpoint = new WebhookEndpoint(
            url: 'https://example.com/webhook'
        );

        $repository->save($endpoint);

        $deactivate = new DeactivateWebhookEndpoint(
            $repository
        );

        $updated = $deactivate->execute($endpoint->url);

        $this->assertFalse($updated->active);
        $this->assertSame(
            $updated,
            $repository->findByUrl($endpoint->url)
        );
    }

    public function testUnknownEndpointCannotBeDeactivated(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $deactivate = new DeactivateWebhookEndpoint(
            $repository
        );

        $this->expectException(\InvalidArgumentException::class);

        $deactivate->execute(
            'https://unknown.example.com/webhook'
        );
    }
}