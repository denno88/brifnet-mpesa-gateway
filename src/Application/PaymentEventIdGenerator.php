<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\Payment;

final class PaymentEventIdGenerator
{
    public function generate(Payment $payment): string
    {
        return hash(
            'sha256',
            'payment.completed|' .
            $payment->reference->value . '|' .
            ($payment->transactionId ?? '')
        );
    }
}