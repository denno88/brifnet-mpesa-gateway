<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Application\ProcessStkCallback;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;

final class ProcessStkCallbackTest extends TestCase
{
    public function testSuccessfulCallbackCompletesPaymentAndRecordsEvent(): void
    {
        $repository = new FakePaymentRepository();
        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

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
                eventRepository: $eventRepository,
                transactionManager: $transactionManager,
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
            ),
        );

        $result = $processor->execute(
            $this->successfulPayload()
        );

        $this->assertTrue($result->isSuccessful());

        $this->assertSame(
            1,
            $repository->updateCount
        );

        $updatedPayment = $repository->payment;

        $this->assertNotNull($updatedPayment);

        $this->assertSame(
            'COMPLETED',
            $updatedPayment->status->value
        );

        $this->assertSame(
            'QAB123XYZ',
            $updatedPayment->transactionId
        );

        $this->assertSame(
            1,
            $eventRepository->saveCount
        );

        $this->assertCount(
            1,
            $eventRepository->events
        );

        $event = array_values(
            $eventRepository->events
        )[0];

        $this->assertSame(
            'payment.completed',
            $event->name()
        );

        $this->assertSame(
            'QAB123XYZ',
            $event->payment->transactionId
        );

        $this->assertSame(
            ['begin', 'commit'],
            $transactionManager->operations
        );
    }

    public function testFailedCallbackMarksPendingPaymentAsFailed(): void
    {
        $repository = new FakePaymentRepository();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2002'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $payment = $payment->attachStkIdentifiers(
            merchantRequestId: '29115-123456790',
            checkoutRequestId: 'ws_CO_987654321',
        );

        $repository->save($payment);

        $processor = $this->processor($repository);

        $result = $processor->execute(
            $this->failedPayload()
        );

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(
            1,
            $repository->updateCount
        );

        $updatedPayment = $repository->payment;

        $this->assertNotNull($updatedPayment);
        $this->assertSame(
            'FAILED',
            $updatedPayment->status->value
        );
    }

    public function testUnknownCheckoutRequestIdIsRejected(): void
    {
        $repository = new FakePaymentRepository();

        $processor = $this->processor($repository);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment not found for checkout request ID.'
        );

        $processor->execute(
            $this->successfulPayload()
        );
    }

    public function testSuccessfulCallbackWithWrongAmountIsRejected(): void
    {
        $repository = $this->repositoryWithPendingPayment(
            amount: 500
        );

        $processor = $this->processor($repository);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Callback amount does not match payment amount.'
        );

        $processor->execute(
            $this->successfulPayload(amount: 1000)
        );

        $this->assertSame(
            0,
            $repository->updateCount
        );
    }

    public function testSuccessfulCallbackWithWrongPhoneIsRejected(): void
    {
        $repository = $this->repositoryWithPendingPayment(
            amount: 500
        );

        $processor = $this->processor($repository);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Callback phone number does not match payment phone number.'
        );

        $processor->execute(
            $this->successfulPayload(
                phone: '0798765432'
            )
        );

        $this->assertSame(
            0,
            $repository->updateCount
        );
    }

    public function testSuccessfulCallbackWithoutReceiptIsRejected(): void
    {
        $repository = $this->repositoryWithPendingPayment(
            amount: 500
        );

        $processor = $this->processor($repository);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Successful callback is missing M-Pesa receipt.'
        );

        $processor->execute(
            $this->successfulPayload(
                includeReceipt: false
            )
        );

        $this->assertSame(
            0,
            $repository->updateCount
        );
    }

    public function testAlreadyCompletedPaymentDoesNotCreateAnotherEvent(): void
    {
        $repository = $this->repositoryWithPendingPayment(
            amount: 500
        );

        $completed = $repository->payment
            ?->attachTransactionId('ORIGINAL123')
            ->complete();

        $this->assertNotNull($completed);

        $repository->payment = $completed;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $processor = new ProcessStkCallback(
            parser: new StkCallbackParser(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: $eventRepository,
                transactionManager: $transactionManager,
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
            ),
        );

        $processor->execute(
            $this->successfulPayload()
        );

        $this->assertSame(
            0,
            $repository->updateCount
        );

        $this->assertSame(
            'COMPLETED',
            $repository->payment?->status->value
        );

        $this->assertSame(
            'ORIGINAL123',
            $repository->payment?->transactionId
        );

        $this->assertSame(
            0,
            $eventRepository->saveCount
        );

        $this->assertSame(
            [],
            $transactionManager->operations
        );
    }

    private function repositoryWithPendingPayment(
        int $amount,
    ): FakePaymentRepository {
        $repository = new FakePaymentRepository();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2003'),
            phone: new PhoneNumber('0712345678'),
            amount: $amount,
            channel: PaymentChannel::STK,
        );

        $payment = $payment->attachStkIdentifiers(
            merchantRequestId: '29115-123456789',
            checkoutRequestId: 'ws_CO_123456789',
        );

        $repository->save($payment);

        return $repository;
    }

    private function successfulPayload(
        int $amount = 500,
        string $phone = '0712345678',
        bool $includeReceipt = true,
    ): string {
        $items = [
            [
                'Name' => 'Amount',
                'Value' => $amount,
            ],
        ];

        if ($includeReceipt) {
            $items[] = [
                'Name' => 'MpesaReceiptNumber',
                'Value' => 'QAB123XYZ',
            ];
        }

        $items[] = [
            'Name' => 'PhoneNumber',
            'Value' => $phone,
        ];

        return json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-123456789',
                    'CheckoutRequestID' => 'ws_CO_123456789',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => $items,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function failedPayload(): string
    {
        return json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-123456790',
                    'CheckoutRequestID' => 'ws_CO_987654321',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function processor(
        FakePaymentRepository $repository,
        ?FakePaymentEventRepository $eventRepository = null,
        ?FakeTransactionManager $transactionManager = null,
    ): ProcessStkCallback {
        $eventRepository ??= new FakePaymentEventRepository();
        $transactionManager ??= new FakeTransactionManager();

        return new ProcessStkCallback(
            parser: new StkCallbackParser(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: $eventRepository,
                transactionManager: $transactionManager,
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
            ),
        );
    }

    public function testSuccessfulCallbackAfterQueryCompletionAttachesReceiptWithoutCreatingAnotherEvent(): void
    {
        $repository = $this->repositoryWithPendingPayment(
            amount: 500
        );

        // Simulate QueryStkPayment having already completed the payment.
        $completed = $repository->payment?->complete();

        $this->assertNotNull($completed);

        $repository->payment = $completed;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $processor = new ProcessStkCallback(
            parser: new StkCallbackParser(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: $eventRepository,
                transactionManager: $transactionManager,
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
            ),
        );

        $processor->execute(
            $this->successfulPayload()
        );

        $updatedPayment = $repository->payment;

        $this->assertNotNull($updatedPayment);

        $this->assertSame(
            'COMPLETED',
            $updatedPayment->status->value
        );

        $this->assertSame(
            'QAB123XYZ',
            $updatedPayment->transactionId
        );

        $this->assertSame(
            1,
            $repository->updateCount
        );

        $this->assertSame(
            0,
            $eventRepository->saveCount
        );

        $this->assertSame(
            [],
            $transactionManager->operations
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
