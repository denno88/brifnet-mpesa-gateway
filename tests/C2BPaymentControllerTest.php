<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\ProcessC2BPayment;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\WordPress\C2BPaymentController;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Application\DefaultPaymentReferenceGenerator;

final class C2BPaymentControllerTest extends TestCase
{
    public function testItAcceptsValidC2BPayment(): void
    {
        $repository = new FakePaymentRepository();

        $processor = new ProcessC2BPayment(
            parser: new \BrifnetMpesa\Api\C2BPaymentParser(),
            validator: new \BrifnetMpesa\Api\C2BPaymentValidator(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
                webhookDeliveryWorker: $this->webhookWorker(),
            ),
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );

        $controller = new C2BPaymentController(
            processor: $processor,
        );

        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => 500,
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902143000',
            'BusinessShortCode' => '123456',
        ], JSON_THROW_ON_ERROR);

        $response = $controller->handle($payload);

        $this->assertSame(
            200,
            $response->statusCode
        );

        $this->assertSame(
            0,
            $response->body['ResultCode']
        );

        $this->assertSame(
            'RKT123456',
            $response->body['TransactionId']
        );
    }

    public function testItReturnsValidationFailure(): void
    {
        $repository = new FakePaymentRepository();

        $processor = new ProcessC2BPayment(
            parser: new \BrifnetMpesa\Api\C2BPaymentParser(),
            validator: new \BrifnetMpesa\Api\C2BPaymentValidator(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
                webhookDeliveryWorker: $this->webhookWorker(),
            ),
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );

        $controller = new C2BPaymentController(
            processor: $processor,
        );

        $response = $controller->handle(
            '{"TransID":"","MSISDN":"0712345678","TransAmount":500}'
        );

        $this->assertSame(
            200,
            $response->statusCode
        );

        $this->assertSame(
            1,
            $response->body['ResultCode']
        );
    }

    public function testItReturnsServerErrorForUnexpectedFailure(): void
    {
        $repository = new FakePaymentRepository();

        $repository->throwUnexpectedExceptionOnSave = true;

        $processor = new ProcessC2BPayment(
            parser: new \BrifnetMpesa\Api\C2BPaymentParser(),
            validator: new \BrifnetMpesa\Api\C2BPaymentValidator(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
                webhookDeliveryWorker: $this->webhookWorker(),
            ),
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );

        $controller = new C2BPaymentController(
            processor: $processor,
        );

        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => 500,
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902143000',
            'BusinessShortCode' => '123456',
        ], JSON_THROW_ON_ERROR);

        $response = $controller->handle($payload);

        $this->assertSame(
            500,
            $response->statusCode
        );

        $this->assertSame(
            1,
            $response->body['ResultCode']
        );

        $this->assertSame(
            'Internal server error.',
            $response->body['ResultDesc']
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