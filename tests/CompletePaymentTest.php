<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Application\WebhookDeliveryWorker;
use BrifnetMpesa\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CompletePaymentTest extends TestCase
{
    public function testItUpdatesPaymentRecordsEventAndCommits(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();
        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $endpointRepository = new FakeWebhookEndpointRepository();
        $deliveryRepository = new FakeWebhookDeliveryRepository();

        $endpointRepository->save(
            new \BrifnetMpesa\Domain\WebhookEndpoint(
                url: 'https://example.com/webhook',
                events: ['payment.completed']
            )
        );

        $webhookQueue = new \BrifnetMpesa\Application\QueuePaymentCompletedWebhooks(
            endpointRepository: $endpointRepository,
            deliveryRepository: $deliveryRepository,
            payloadBuilder: new \BrifnetMpesa\Application\PaymentCompletedPayloadBuilder(),
        );

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $webhookQueue,
            webhookDeliveryWorker: $this->webhookWorker(
                $deliveryRepository
            ),
        );

        $service->execute($payment);

        self::assertSame(1, $paymentRepository->updateCount);
        self::assertSame(1, $eventRepository->saveCount);
        self::assertSame(
            ['begin', 'commit'],
            $transactionManager->operations
        );

        self::assertCount(1, $eventRepository->events);

        $event = array_values($eventRepository->events)[0];

        self::assertSame(
            'payment.completed',
            $event->name()
        );

        self::assertSame(
            $payment->reference->value,
            $event->payment->reference->value
        );

        self::assertCount(
            1,
            $deliveryRepository->deliveries
        );

        $delivery = $deliveryRepository->findByEventIdAndUrl(
            $event->eventId,
            'https://example.com/webhook'
        );

        self::assertNotNull($delivery);

        self::assertSame(
            'delivered',
            $delivery->status
        );
    }

    public function testItRollsBackWhenEventRecordingFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();
        $eventRepository = new class implements \BrifnetMpesa\Domain\PaymentEventRepository {
            public function save(PaymentCompleted $event): void
            {
                throw new RuntimeException(
                    'Event storage failed.'
                );
            }

            public function findByEventId(
                string $eventId
            ): ?PaymentCompleted {
                return null;
            }
        };

        $transactionManager = new FakeTransactionManager();

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event storage failed.');

        try {
            $service->execute($payment);
        } finally {
            self::assertSame(
                ['begin', 'rollback'],
                $transactionManager->operations
            );
        }
    }

    public function testItDoesNotCommitWhenEventRecordingFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();

        $eventRepository = new class implements \BrifnetMpesa\Domain\PaymentEventRepository {
            public function save(PaymentCompleted $event): void
            {
                throw new RuntimeException(
                    'Event storage failed.'
                );
            }

            public function findByEventId(
                string $eventId
            ): ?PaymentCompleted {
                return null;
            }
        };

        $transactionManager = new FakeTransactionManager();

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        try {
            $service->execute($payment);
        } catch (RuntimeException) {
            // Expected.
        }

        self::assertNotContains(
            'commit',
            $transactionManager->operations
        );
    }

    private function pendingPayment(): Payment
    {
        return new Payment(
            reference: new PaymentReference('INV-1001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::PENDING,
            merchantRequestId: 'MR-1001',
            checkoutRequestId: 'CR-1001',
        );
    }

    public function testItRollsBackWhenPaymentUpdateFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();
        $paymentRepository->saveShouldFail = false;

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        // We need update() to fail, so use an anonymous repository
        // implementing only the behavior required by this test.
        $paymentRepository = new class implements \BrifnetMpesa\Domain\PaymentRepository {
            public function save(\BrifnetMpesa\Domain\Payment $payment): void
            {
            }

            public function update(\BrifnetMpesa\Domain\Payment $payment): void
            {
                throw new RuntimeException('Payment update failed.');
            }

            public function findByCheckoutRequestId(
                string $checkoutRequestId
            ): ?\BrifnetMpesa\Domain\Payment {
                return null;
            }

            public function findByTransactionId(
                string $transactionId
            ): ?\BrifnetMpesa\Domain\Payment {
                return null;
            }
        };

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment update failed.');

        try {
            $service->execute($payment);
        } finally {
            self::assertSame(
                ['begin', 'rollback'],
                $transactionManager->operations
            );

            self::assertSame(
                0,
                $eventRepository->saveCount
            );
        }
    }

    public function testItRollsBackWhenNewPaymentEventRecordingFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();

        $eventRepository = new class implements \BrifnetMpesa\Domain\PaymentEventRepository {
            public function save(PaymentCompleted $event): void
            {
                throw new RuntimeException(
                    'Event storage failed.'
                );
            }

            public function findByEventId(
                string $eventId
            ): ?PaymentCompleted {
                return null;
            }
        };

        $transactionManager = new FakeTransactionManager();

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event storage failed.');

        try {
            $service->executeNew($payment);
        } finally {
            self::assertSame(
                ['begin', 'rollback'],
                $transactionManager->operations
            );
        }
    }

    public function testItRollsBackWhenNewPaymentSaveFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new class implements \BrifnetMpesa\Domain\PaymentRepository {
            public function save(\BrifnetMpesa\Domain\Payment $payment): void
            {
                throw new RuntimeException(
                    'Payment save failed.'
                );
            }

            public function update(\BrifnetMpesa\Domain\Payment $payment): void
            {
            }

            public function findByCheckoutRequestId(
                string $checkoutRequestId
            ): ?\BrifnetMpesa\Domain\Payment {
                return null;
            }

            public function findByTransactionId(
                string $transactionId
            ): ?\BrifnetMpesa\Domain\Payment {
                return null;
            }
        };

        $eventRepository = new FakePaymentEventRepository();
        $transactionManager = new FakeTransactionManager();

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $this->webhookQueue(),
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment save failed.');

        try {
            $service->executeNew($payment);
        } finally {
            self::assertSame(
                ['begin', 'rollback'],
                $transactionManager->operations
            );

            self::assertSame(
                0,
                $eventRepository->saveCount
            );
        }
    }

    private function webhookQueue(): QueuePaymentCompletedWebhooks
    {
        return new QueuePaymentCompletedWebhooks(
            endpointRepository: new FakeWebhookEndpointRepository(),
            deliveryRepository: new FakeWebhookDeliveryRepository(),
            payloadBuilder: new PaymentCompletedPayloadBuilder(),
        );
    }

    public function testItRollsBackWhenWebhookDeliveryCreationFails(): void
    {
        $payment = $this->pendingPayment()->complete();

        $paymentRepository = new FakePaymentRepository();
        $eventRepository = new FakePaymentEventRepository();

        $endpointRepository = new FakeWebhookEndpointRepository();
        $endpointRepository->save(
            new \BrifnetMpesa\Domain\WebhookEndpoint(
                url: 'https://example.com/webhook',
                events: ['payment.completed']
            )
        );

        $deliveryRepository = new class implements \BrifnetMpesa\Domain\WebhookDeliveryRepository {
            public function save(\BrifnetMpesa\Domain\WebhookDelivery $delivery): void
            {
                throw new RuntimeException(
                    'Webhook delivery creation failed.'
                );
            }

            public function findByEventIdAndUrl(
                string $eventId,
                string $url
            ): ?\BrifnetMpesa\Domain\WebhookDelivery {
                return null;
            }

            public function findPending(): array
            {
                return [];
            }

            public function update(
                \BrifnetMpesa\Domain\WebhookDelivery $delivery
            ): void {
            }
        };

        $transactionManager = new FakeTransactionManager();

        $webhookQueue = new QueuePaymentCompletedWebhooks(
            endpointRepository: $endpointRepository,
            deliveryRepository: $deliveryRepository,
            payloadBuilder: new PaymentCompletedPayloadBuilder(),
        );

        $service = new CompletePayment(
            paymentRepository: $paymentRepository,
            eventRepository: $eventRepository,
            transactionManager: $transactionManager,
            eventIdGenerator: new PaymentEventIdGenerator(),
            webhookQueue: $webhookQueue,
            webhookDeliveryWorker: $this->webhookWorker(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Webhook delivery creation failed.'
        );

        try {
            $service->execute($payment);
        } finally {
            self::assertSame(
                ['begin', 'rollback'],
                $transactionManager->operations
            );

            self::assertNotContains(
                'commit',
                $transactionManager->operations
            );
        }
    }

    private function webhookWorker(
        ?FakeWebhookDeliveryRepository $repository = null
    ): WebhookDeliveryWorker {
        return new WebhookDeliveryWorker(
            repository: $repository ?? new FakeWebhookDeliveryRepository(),
            httpClient: new FakeWebhookHttpClient(),
            signature: new WebhookSignature(),
            webhookSecret: 'test-secret',
        );
    }
}