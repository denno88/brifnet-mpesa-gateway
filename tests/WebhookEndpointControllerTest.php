<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use BrifnetMpesa\WordPress\RestResponse;
use BrifnetMpesa\WordPress\WebhookEndpointController;
use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class WebhookEndpointControllerTest extends TestCase
{
    public function testValidWebhookCanBeRegistered(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            json_encode([
                'url' => 'https://example.com/webhook',
                'events' => [
                    'payment.completed',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(200, $response->statusCode);

        $this->assertSame(
            'https://example.com/webhook',
            $response->body['url']
        );

        $this->assertSame(
            ['payment.completed'],
            $response->body['events']
        );

        $this->assertTrue($response->body['active']);
    }

    public function testDuplicateWebhookIsRejected(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $payload = json_encode([
            'url' => 'https://example.com/webhook',
            'events' => [
                'payment.completed',
            ],
        ], JSON_THROW_ON_ERROR);

        $controller->handle($payload);

        $response = $controller->handle($payload);

        $this->assertSame(400, $response->statusCode);

        $this->assertSame(
            'Webhook endpoint already exists.',
            $response->body['message']
        );
    }

    public function testMalformedJsonIsRejected(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            '{"url":"https://example.com/webhook",'
        );

        $this->assertSame(400, $response->statusCode);

        $this->assertSame(
            'Invalid JSON payload.',
            $response->body['message']
        );
    }

    public function testMissingUrlIsRejected(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            json_encode([
                'events' => [
                    'payment.completed',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $response->statusCode);

        $this->assertSame(
            'Webhook URL cannot be empty.',
            $response->body['message']
        );
    }

    public function testEventsMustBeAnArray(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            json_encode([
                'url' => 'https://example.com/webhook',
                'events' => 'payment.completed',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $response->statusCode);
    }

    public function testEventNamesMustBeStrings(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            json_encode([
                'url' => 'https://example.com/webhook',
                'events' => [
                    'payment.completed',
                    123,
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $response->statusCode);
    }

    public function testEmptyEventNameIsRejected(): void
    {
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $response = $controller->handle(
            json_encode([
                'url' => 'https://example.com/webhook',
                'events' => [
                    '',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $response->statusCode);

        $this->assertSame(
            'Webhook event name cannot be empty.',
            $response->body['message']
        );
    }
}