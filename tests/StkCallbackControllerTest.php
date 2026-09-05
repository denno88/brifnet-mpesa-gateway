<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\ProcessStkCallback;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\WordPress\StkCallbackController;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;

final class StkCallbackControllerTest extends TestCase
{
    public function testSuccessfulCallbackReturnsAcceptedResponse(): void
    {
        $repository = new FakePaymentRepository();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $payment = $payment->attachStkIdentifiers(
            merchantRequestId: '29115-123456789',
            checkoutRequestId: 'ws_CO_123456789',
        );

        $repository->save($payment);

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

        $response = $controller->handle(
            $this->successfulPayload()
        );

        $this->assertSame(
            200,
            $response->statusCode
        );

        $this->assertSame(
            [
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ],
            $response->body
        );
    }

    public function testInvalidCallbackReturnsClientSafeError(): void
    {
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

        $response = $controller->handle(
            '{"invalid":true}'
        );

        $this->assertSame(
            200,
            $response->statusCode
        );

        $this->assertSame(
            [
                'ResultCode' => 1,
                'ResultDesc' => 'Callback could not be processed.',
            ],
            $response->body
        );
    }

    public function testUnknownPaymentReturnsClientSafeError(): void
    {
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

        $response = $controller->handle(
            $this->successfulPayload()
        );

        $this->assertSame(
            200,
            $response->statusCode
        );

        $this->assertSame(
            [
                'ResultCode' => 1,
                'ResultDesc' => 'Callback could not be processed.',
            ],
            $response->body
        );
    }

    private function successfulPayload(): string
    {
        return json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-123456789',
                    'CheckoutRequestID' => 'ws_CO_123456789',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            [
                                'Name' => 'Amount',
                                'Value' => 500,
                            ],
                            [
                                'Name' => 'MpesaReceiptNumber',
                                'Value' => 'QWE123456',
                            ],
                            [
                                'Name' => 'PhoneNumber',
                                'Value' => '0712345678',
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
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