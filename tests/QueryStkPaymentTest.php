<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkQueryResult;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\QueryStkPayment;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;

final class QueryStkPaymentTest extends TestCase
{
    public function testSuccessfulQueryCompletesPendingPayment(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('REF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::PENDING,
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_123456789',
        );

        $paymentRepository = new FakePaymentRepository();
        $paymentRepository->payment = $payment;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $completePayment = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
        );

        $mpesaClient = new FakeMpesaClient();

        $mpesaClient->queryResult = new StkQueryResult(
            successful: true,
            responseCode: '0',
            responseDescription:
                'The service request is processed successfully.',
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_123456789',
            resultCode: '0',
            resultDescription:
                'The service request is processed successfully.',
        );

        $service = new QueryStkPayment(
            paymentRepository: $paymentRepository,
            mpesaClient: $mpesaClient,
            completePayment: $completePayment,
        );

        $result = $service->execute(
            'ws_CO_123456789'
        );

        self::assertTrue($result->successful);

        self::assertSame(
            1,
            $mpesaClient->queryCallCount
        );

        self::assertSame(
            'ws_CO_123456789',
            $mpesaClient->queriedCheckoutRequestId
        );

        self::assertSame(
            PaymentStatus::COMPLETED,
            $paymentRepository->payment->status
        );

        self::assertSame(
            1,
            $eventRepository->saveCount
        );

        self::assertSame(
            ['begin', 'commit'],
            $transactionManager->operations
        );
    }

    public function testUnsuccessfulQueryFailsPendingPayment(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('REF-002'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::PENDING,
            merchantRequestId: '22205-34066-1',
            checkoutRequestId: 'ws_CO_13012021093521236557',
        );

        $paymentRepository = new FakePaymentRepository();
        $paymentRepository->payment = $payment;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $completePayment = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
        );

        $mpesaClient = new FakeMpesaClient();

        $mpesaClient->queryResult = new StkQueryResult(
            successful: false,
            responseCode: '0',
            responseDescription:
                'The service request has been accepted successfully.',
            merchantRequestId: '22205-34066-1',
            checkoutRequestId: 'ws_CO_13012021093521236557',
            resultCode: '1032',
            resultDescription: 'Request cancelled by user.',
        );

        $service = new QueryStkPayment(
            paymentRepository: $paymentRepository,
            mpesaClient: $mpesaClient,
            completePayment: $completePayment,
        );

        $service->execute(
            'ws_CO_13012021093521236557'
        );

        self::assertSame(
            PaymentStatus::FAILED,
            $paymentRepository->payment?->status
        );

        self::assertSame(
            1,
            $paymentRepository->updateCount
        );

        self::assertSame(
            0,
            $eventRepository->saveCount
        );
    }

    public function testSuccessfulQueryDoesNotCompleteAlreadyCompletedPayment(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('REF-003'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::COMPLETED,
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_987654321',
        );

        $paymentRepository = new FakePaymentRepository();
        $paymentRepository->payment = $payment;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $completePayment = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
        );

        $mpesaClient = new FakeMpesaClient();

        $mpesaClient->queryResult = new StkQueryResult(
            successful: true,
            responseCode: '0',
            responseDescription:
                'The service request has been accepted successfully.',
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_987654321',
            resultCode: '0',
            resultDescription:
                'The service request is processed successfully.',
        );

        $service = new QueryStkPayment(
            paymentRepository: $paymentRepository,
            mpesaClient: $mpesaClient,
            completePayment: $completePayment,
        );

        $service->execute(
            'ws_CO_987654321'
        );

        self::assertSame(
            PaymentStatus::COMPLETED,
            $paymentRepository->payment?->status
        );

        self::assertSame(
            0,
            $paymentRepository->updateCount
        );

        self::assertSame(
            0,
            $eventRepository->saveCount
        );

        self::assertSame(
            [],
            $transactionManager->operations
        );
    }

    public function testSuccessfulQueryDoesNotResurrectFailedPayment(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('REF-004'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::FAILED,
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_555555555',
        );

        $paymentRepository = new FakePaymentRepository();
        $paymentRepository->payment = $payment;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $completePayment = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
        );

        $mpesaClient = new FakeMpesaClient();

        $mpesaClient->queryResult = new StkQueryResult(
            successful: true,
            responseCode: '0',
            responseDescription:
                'The service request has been accepted successfully.',
            merchantRequestId: '29115-34620561-1',
            checkoutRequestId: 'ws_CO_555555555',
            resultCode: '0',
            resultDescription:
                'The service request is processed successfully.',
        );

        $service = new QueryStkPayment(
            paymentRepository: $paymentRepository,
            mpesaClient: $mpesaClient,
            completePayment: $completePayment,
        );

        $service->execute(
            'ws_CO_555555555'
        );

        self::assertSame(
            PaymentStatus::FAILED,
            $paymentRepository->payment?->status
        );

        self::assertSame(
            0,
            $paymentRepository->updateCount
        );

        self::assertSame(
            0,
            $eventRepository->saveCount
        );

        self::assertSame(
            [],
            $transactionManager->operations
        );
    }

    public function testItThrowsWhenPaymentIsNotFound(): void
    {
        $paymentRepository = new FakePaymentRepository();

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $completePayment = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
        );

        $mpesaClient = new FakeMpesaClient();

        $service = new QueryStkPayment(
            paymentRepository: $paymentRepository,
            mpesaClient: $mpesaClient,
            completePayment: $completePayment,
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage(
            'Payment not found for checkout request ID.'
        );

        $service->execute(
            'ws_CO_UNKNOWN'
        );

        self::assertSame(
            0,
            $mpesaClient->queryCallCount
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