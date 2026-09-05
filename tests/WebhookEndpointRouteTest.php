<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use BrifnetMpesa\WordPress\WebhookEndpointController;
use BrifnetMpesa\WordPress\WebhookEndpointRoute;
use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class WebhookEndpointRouteTest extends TestCase
{
    public function testWebhookRegistrationRouteIsRegistered(): void
    {
        $registrar = new FakeRestRegistrar();
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $route = new WebhookEndpointRoute(
            $registrar,
            $controller
        );

        $route->register();

       $this->assertCount(3, $registrar->routes);

        $this->assertSame(
            'brifnet/v1',
            $registrar->routes[0]['namespace']
        );

        $this->assertSame(
            '/webhooks',
            $registrar->routes[0]['route']
        );

        $this->assertSame(
            'POST',
            $registrar->routes[0]['args']['methods']
        );
    }

    public function testWebhookRouteCallbackProcessesRequest(): void
    {
        $registrar = new FakeRestRegistrar();
        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $route = new WebhookEndpointRoute(
            $registrar,
            $controller
        );

        $route->register();

        $request = new class {
            public function get_body(): string
            {
                return json_encode([
                    'url' => 'https://example.com/webhook',
                    'events' => [
                        'payment.completed',
                    ],
                ], JSON_THROW_ON_ERROR);
            }
        };

        $callback = $registrar->routes[0]['args']['callback'];

        $response = $callback($request);

        $this->assertSame(200, $response->statusCode);

        $this->assertSame(
            'https://example.com/webhook',
            $response->body['url']
        );

        $this->assertArrayHasKey(
            'https://example.com/webhook',
            $repository->endpoints
        );
    }
}