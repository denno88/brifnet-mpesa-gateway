<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentEventRepository;
use BrifnetMpesa\Domain\PaymentRepository;
use BrifnetMpesa\Domain\PaymentCompleted;

final class CompletePayment
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentEventRepository $eventRepository,
        private readonly TransactionManager $transactionManager,
        private readonly PaymentEventIdGenerator $eventIdGenerator,
        private readonly QueuePaymentCompletedWebhooks $webhookQueue,
    ) {
    }

    public function execute(Payment $payment): void
    {
        $this->transactionManager->begin();

        try {
            $this->paymentRepository->update($payment);

            $event = new PaymentCompleted(
                eventId: $this->eventIdGenerator->generate($payment),
                payment: $payment,
                occurredAt: new \DateTimeImmutable(),
            );

            $this->eventRepository->save($event);

            $this->webhookQueue->queue($event);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            throw $exception;
        }
    }

    public function executeNew(Payment $payment): void
    {
        $this->transactionManager->begin();

        try {
            $this->paymentRepository->save($payment);

            $event = new PaymentCompleted(
                eventId: $this->eventIdGenerator->generate($payment),
                payment: $payment,
                occurredAt: new \DateTimeImmutable(),
            );

            $this->eventRepository->save($event);

            $this->webhookQueue->queue($event);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            throw $exception;
        }
    }
}