<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\ProcessStkCallback;
use BrifnetMpesa\WordPress\StkCallbackController;
use BrifnetMpesa\WordPress\StkCallbackRoute;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;

final class StkCallbackRouteTest extends TestCase
{
    public function testCallbackRouteIsRegistered(): void
    {
        $registrar = new FakeRestRegistrar();

        $repository = new FakePaymentRepository();

        $processor = new ProcessStkCallback(
            parser: new StkCallbackParser(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
            ),
        );

        $controller = new StkCallbackController($processor);

        $route = new StkCallbackRoute(
            registrar: $registrar,
            controller: $controller,
        );

        $route->register();

        $this->assertCount(
            1,
            $registrar->routes
        );

        $registered = $registrar->routes[0];

        $this->assertSame(
            'brifnet/v1',
            $registered['namespace']
        );

        $this->assertSame(
            '/mpesa/callback',
            $registered['route']
        );

        $this->assertSame(
            'POST',
            $registered['args']['methods']
        );

        $this->assertSame(
            '__return_true',
            $registered['args']['permission_callback']
        );

        $this->assertIsCallable(
            $registered['args']['callback']
        );
    }

    private function webhookQueue(): QueuePaymentCompletedWebhooks
    {
        return new QueuePaymentCompletedWebhooks(
            endpointRepository: new FakeWebhookEndpointRepository(),
            deliveryRepository: new FakeWebhookDeliveryRepository(),
            payloadBuilder: new PaymentCompletedPayloadBuilder(),
        );
    }
}