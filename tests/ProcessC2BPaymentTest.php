<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\C2BPaymentParser;
use BrifnetMpesa\Api\C2BPaymentValidator;
use BrifnetMpesa\Application\ProcessC2BPayment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Application\DefaultPaymentReferenceGenerator;


final class ProcessC2BPaymentTest extends TestCase
{
    private ProcessC2BPayment $processor;

    private FakePaymentRepository $repository;

    private CompletePayment $completePayment;

    private FakePaymentEventRepository $eventRepository;

    private FakeTransactionManager $transactionManager;

    protected function setUp(): void
    {
        $this->repository = new FakePaymentRepository();
        $this->eventRepository = new FakePaymentEventRepository();
        $this->transactionManager = new FakeTransactionManager();

        $this->completePayment = new CompletePayment(
            paymentRepository: $this->repository,
            eventRepository: $this->eventRepository,
            transactionManager: $this->transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->processor = new ProcessC2BPayment(
            parser: new C2BPaymentParser(),
            validator: new C2BPaymentValidator(),
            paymentRepository: $this->repository,
            completePayment: $this->completePayment,
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );
    }

    public function testItProcessesValidC2BPayment(): void
    {
        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $payment = $this->processor->execute($payload);

        $this->assertSame(
            'RKT123456',
            $payment->transactionId
        );

        $this->assertSame(
            '0712345678',
            $payment->phone->value
        );

        $this->assertSame(
            500,
            $payment->amount
        );

        $this->assertSame(
            PaymentChannel::C2B,
            $payment->channel
        );

        $this->assertSame(
            PaymentStatus::COMPLETED,
            $payment->status
        );

        $this->assertCount(
            1,
            $this->repository->savedPayments
        );

        $this->assertSame(
            1,
            $this->eventRepository->saveCount
        );

        $this->assertSame(
            ['begin', 'commit'],
            $this->transactionManager->operations
        );

        $this->assertCount(
            1,
            $this->eventRepository->events
        );

        $event = array_values(
            $this->eventRepository->events
        )[0];

        $this->assertSame(
            'payment.completed',
            $event->name()
        );

        $this->assertSame(
            $payment->reference->value,
            $event->payment->reference->value
        );
    }

    public function testItDoesNotCreateDuplicatePayment(): void
    {
        $existingPayment = $this->createCompletedPayment(
            transactionId: 'RKT123456'
        );

        $this->repository->paymentsByTransactionId[
            'RKT123456'
        ] = $existingPayment;

        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $payment = $this->processor->execute($payload);

        $this->assertSame(
            $existingPayment,
            $payment
        );

        $this->assertCount(
            0,
            $this->repository->savedPayments
        );

        $this->assertSame(
            0,
            $this->eventRepository->saveCount
        );

        $this->assertSame(
            [],
            $this->transactionManager->operations
        );
    }

    public function testItRejectsInvalidC2BPayment(): void
    {
        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '12345',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid phone number.'
        );

        $this->processor->execute($payload);

        $this->assertCount(
            0,
            $this->repository->savedPayments
        );
    }

    public function testItDoesNotCallRepositorySaveWhenPaymentAlreadyExists(): void
    {
        $existingPayment = $this->createCompletedPayment(
            transactionId: 'RKT999999'
        );

        $this->repository->paymentsByTransactionId[
            'RKT999999'
        ] = $existingPayment;

        $payload = json_encode([
            'TransID' => 'RKT999999',
            'MSISDN' => '0712345678',
            'TransAmount' => '1000',
            'BillRefNumber' => 'BRIF-002',
            'TransTime' => '20260902130000',
            'BusinessShortCode' => '123456',
        ]);

        $this->processor->execute($payload);

        $this->assertCount(
            0,
            $this->repository->savedPayments
        );
    }

    private function createCompletedPayment(
        string $transactionId
    ): \BrifnetMpesa\Domain\Payment {
        return new \BrifnetMpesa\Domain\Payment(
            reference: new \BrifnetMpesa\Domain\PaymentReference(
                'BRIF-001'
            ),
            phone: new \BrifnetMpesa\Domain\PhoneNumber(
                '0712345678'
            ),
            amount: 500,
            channel: PaymentChannel::C2B,
            status: PaymentStatus::COMPLETED,
            transactionId: $transactionId,
        );
    }

    public function testItReturnsExistingPaymentWhenConcurrentInsertCausesDuplicate(): void
    {
        $repository = new FakePaymentRepository();
        $repository->throwDuplicateOnSave = true;

        $service = new ProcessC2BPayment(
            parser: new C2BPaymentParser(),
            validator: new C2BPaymentValidator(),
            paymentRepository: $repository,
            completePayment: $this->completePayment,
            referenceGenerator: new DefaultPaymentReferenceGenerator(),
        );

        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => 500,
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902143000',
            'BusinessShortCode' => '123456',
        ], JSON_THROW_ON_ERROR);

        $existingPayment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::C2B,
            status: PaymentStatus::COMPLETED,
            transactionId: 'RKT123456',
        );

        $repository->paymentsByTransactionId['RKT123456'] =
            $existingPayment;

        $result = $service->execute($payload);

        $this->assertSame(
            $existingPayment,
            $result
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