<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Api\StkCallbackResult;
use BrifnetMpesa\Domain\PaymentRepository;
use InvalidArgumentException;

final class ProcessStkCallback
{
    public function __construct(
        private readonly StkCallbackParser $parser,
        private readonly PaymentRepository $paymentRepository,
        private readonly CompletePayment $completePayment,
    ) {}

    public function execute(string $payload): StkCallbackResult
    {
        $callback = $this->parser->parse($payload);

        $payment = $this->paymentRepository
            ->findByCheckoutRequestId(
                $callback->checkoutRequestId
            );

        if ($payment === null) {
            throw new InvalidArgumentException(
                'Payment not found for checkout request ID.'
            );
        }

        if (!$callback->isSuccessful()) {
            if ($payment->status->value === 'PENDING') {
                $this->paymentRepository->update(
                    $payment->fail()
                );
            }

            return $callback;
        }

        if ($callback->amount === null) {
            throw new InvalidArgumentException(
                'Successful callback is missing amount.'
            );
        }

        if ($callback->receipt === null || $callback->receipt === '') {
            throw new InvalidArgumentException(
                'Successful callback is missing M-Pesa receipt.'
            );
        }

        if ($callback->amount !== $payment->amount) {
            throw new InvalidArgumentException(
                'Callback amount does not match payment amount.'
            );
        }

        if (
            $callback->phone !== null &&
            $callback->phone !== $payment->phone->value
        ) {
            throw new InvalidArgumentException(
                'Callback phone number does not match payment phone number.'
            );
        }

        if ($payment->status->value === 'PENDING') {
            $completedPayment = $payment
                ->attachTransactionId($callback->receipt)
                ->complete();

            $this->completePayment->execute($completedPayment);
        } elseif (
            $payment->status->value === 'COMPLETED' &&
            $payment->transactionId === null
        ) {
            $paymentWithReceipt = $payment->attachTransactionId(
                $callback->receipt
            );

            $this->paymentRepository->update(
                $paymentWithReceipt
            );
        }

        return $callback;
    }
}
