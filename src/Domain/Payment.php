<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

use InvalidArgumentException;

final readonly class Payment
{
    public function __construct(
        public PaymentReference $reference,
        public PhoneNumber $phone,
        public int $amount,
        public PaymentChannel $channel,
        public PaymentStatus $status = PaymentStatus::PENDING,
        public ?string $merchantRequestId = null,
        public ?string $checkoutRequestId = null,
        public ?string $transactionId = null,
        public ?string $accountNumber = null,
    ) {
        if ($this->amount <= 0) {
            throw new InvalidArgumentException(
                'Payment amount must be greater than zero.'
            );
        }
    }

    public function attachStkIdentifiers(
        string $merchantRequestId,
        string $checkoutRequestId,
    ): self {
        if ($merchantRequestId === '') {
            throw new InvalidArgumentException(
                'Merchant request ID cannot be empty.'
            );
        }

        if ($checkoutRequestId === '') {
            throw new InvalidArgumentException(
                'Checkout request ID cannot be empty.'
            );
        }

        return new self(
            reference: $this->reference,
            phone: $this->phone,
            amount: $this->amount,
            channel: $this->channel,
            status: $this->status,
            merchantRequestId: $merchantRequestId,
            checkoutRequestId: $checkoutRequestId,
            transactionId: $this->transactionId,
            accountNumber: $this->accountNumber,
        );
    }

    public function attachTransactionId(
        string $transactionId,
    ): self {
        if ($transactionId === '') {
            throw new InvalidArgumentException(
                'Transaction ID cannot be empty.'
            );
        }

        return new self(
            reference: $this->reference,
            phone: $this->phone,
            amount: $this->amount,
            channel: $this->channel,
            status: $this->status,
            merchantRequestId: $this->merchantRequestId,
            checkoutRequestId: $this->checkoutRequestId,
            transactionId: $transactionId,
            accountNumber: $this->accountNumber,
        );
    }

    public function complete(): self
    {
        if ($this->status !== PaymentStatus::PENDING) {
            throw new InvalidArgumentException(
                'Only pending payments can be completed.'
            );
        }

        return new self(
            reference: $this->reference,
            phone: $this->phone,
            amount: $this->amount,
            channel: $this->channel,
            status: PaymentStatus::COMPLETED,
            merchantRequestId: $this->merchantRequestId,
            checkoutRequestId: $this->checkoutRequestId,
            transactionId: $this->transactionId,
            accountNumber: $this->accountNumber,
        );
    }

    public function fail(): self
    {
        if ($this->status !== PaymentStatus::PENDING) {
            throw new InvalidArgumentException(
                'Only pending payments can be failed.'
            );
        }

        return new self(
            reference: $this->reference,
            phone: $this->phone,
            amount: $this->amount,
            channel: $this->channel,
            status: PaymentStatus::FAILED,
            merchantRequestId: $this->merchantRequestId,
            checkoutRequestId: $this->checkoutRequestId,
            transactionId: $this->transactionId,
            accountNumber: $this->accountNumber,
        );
    }
}