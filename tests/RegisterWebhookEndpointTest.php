<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class RegisterWebhookEndpointTest extends TestCase
{
    public function testEndpointCanBeRegistered(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $register = new RegisterWebhookEndpoint(
            $repository
        );

        $endpoint = $register->execute(
            'https://example.com/webhook',
            ['payment.completed']
        );

        $this->assertSame(
            'https://example.com/webhook',
            $endpoint->url
        );

        $this->assertSame(
            $endpoint,
            $repository->findByUrl(
                'https://example.com/webhook'
            )
        );
    }

    public function testRegisteredEndpointIsActive(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $register = new RegisterWebhookEndpoint(
            $repository
        );

        $endpoint = $register->execute(
            'https://example.com/webhook',
            ['payment.completed']
        );

        $this->assertTrue($endpoint->active);
    }

    public function testDuplicateUrlIsRejected(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $register = new RegisterWebhookEndpoint(
            $repository
        );

        $register->execute(
            'https://example.com/webhook',
            ['payment.completed']
        );

        $this->expectException(\InvalidArgumentException::class);

        $register->execute(
            'https://example.com/webhook',
            ['payment.failed']
        );
    }
}