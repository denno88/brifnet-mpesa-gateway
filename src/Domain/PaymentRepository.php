<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

interface PaymentRepository
{
    public function save(Payment $payment): void;

    public function update(Payment $payment): void;

    public function findByCheckoutRequestId(
        string $checkoutRequestId
    ): ?Payment;

    public function findByTransactionId(
        string $transactionId
    ): ?Payment;
}