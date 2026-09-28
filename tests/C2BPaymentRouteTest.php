<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\C2BPaymentController;
use BrifnetMpesa\WordPress\C2BPaymentRoute;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\ProcessC2BPayment;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Application\DefaultPaymentReferenceGenerator;

final class C2BPaymentRouteTest extends TestCase
{
    public function testItRegistersC2BRoute(): void
    {
        $registrar = new FakeRestRegistrar();

        $repository = new FakePaymentRepository();

        $processor = new ProcessC2BPayment(
            parser: new \BrifnetMpesa\Api\C2BPaymentParser(),
            validator: new \BrifnetMpesa\Api\C2BPaymentValidator(),
            paymentRepository: $repository,
            completePayment: new \BrifnetMpesa\Application\CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new \BrifnetMpesa\Application\PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
                webhookDeliveryWorker: $this->webhookWorker(),
            ),
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );

        $controller = new C2BPaymentController(
            processor: $processor,
        );

        $route = new C2BPaymentRoute(
            registrar: $registrar,
            controller: $controller,
        );

        $route->register();

        $this->assertSame(
            'brifnet/v1',
            $registrar->namespace
        );

        $this->assertSame(
            '/mpesa/c2b',
            $registrar->route
        );

        $this->assertSame(
            'POST',
            $registrar->args['methods']
        );

        $this->assertSame(
            '__return_true',
            $registrar->args['permission_callback']
        );

        $this->assertIsCallable(
            $registrar->args['callback']
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

    private function webhookWorker(): \BrifnetMpesa\Application\WebhookDeliveryWorker
    {
        return new \BrifnetMpesa\Application\WebhookDeliveryWorker(
            repository: new FakeWebhookDeliveryRepository(),
            httpClient: new FakeWebhookHttpClient(),
            signature: new \BrifnetMpesa\Domain\WebhookSignature(),
            webhookSecret: 'test-secret',
        );
    }
}