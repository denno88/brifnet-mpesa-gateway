<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentRepository;
use RuntimeException;

final class FakePaymentRepository implements PaymentRepository
{
    public ?Payment $payment = null;

    public int $saveCount = 0;

    public int $updateCount = 0;

    public bool $saveShouldFail = false;

    public array $paymentsByTransactionId = [];

    public array $savedPayments = [];

    public bool $throwDuplicateOnSave = false;

    public bool $throwUnexpectedExceptionOnSave = false;

    public function save(Payment $payment): void
    {
        $this->saveCount++;
        $this->savedPayments[] = $payment;

        if ($this->throwDuplicateOnSave) {
            throw new \BrifnetMpesa\Domain\DuplicatePaymentException(
                'Payment already exists.'
            );
        }

        if ($this->throwUnexpectedExceptionOnSave) {
            throw new \RuntimeException(
                'Database failure.'
            );
        }
        
        if ($this->saveShouldFail) {
            throw new RuntimeException(
                'Failed to save payment.'
            );
        }

        $this->payment = $payment;
    }

    public function update(Payment $payment): void
    {
        $this->updateCount++;
        $this->payment = $payment;
    }

    public function findByCheckoutRequestId(
        string $checkoutRequestId
    ): ?Payment {
        if ($this->payment === null) {
            return null;
        }

        if (
            $this->payment->checkoutRequestId !==
            $checkoutRequestId
        ) {
            return null;
        }

        return $this->payment;
    }

    public function findByTransactionId(
        string $transactionId
    ): ?Payment {
        return $this->paymentsByTransactionId[$transactionId] ?? null;
    }
}
