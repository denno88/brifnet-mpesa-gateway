<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

final readonly class PaymentCompleted
{
    public function __construct(
        public string $eventId,
        public Payment $payment,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'payment.completed';
    }
}