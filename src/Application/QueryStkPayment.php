<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Api\MpesaClient;
use BrifnetMpesa\Api\StkQueryResult;
use BrifnetMpesa\Domain\PaymentRepository;

final class QueryStkPayment
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly MpesaClient $mpesaClient,
        private readonly CompletePayment $completePayment,
    ) {
    }

    public function execute(
        string $checkoutRequestId
    ): StkQueryResult {
        $payment = $this->paymentRepository
            ->findByCheckoutRequestId($checkoutRequestId);

        if ($payment === null) {
            throw new \InvalidArgumentException(
                'Payment not found for checkout request ID.'
            );
        }

        $result = $this->mpesaClient->queryStkPush(
            $checkoutRequestId
        );

        if (
            $result->successful &&
            $result->resultCode === '0' &&
            $payment->status->value === 'PENDING'
        ) {
            $completedPayment = $payment->complete();

            $this->completePayment->execute(
                $completedPayment
            );
        }

        if (
            !$result->successful &&
            $payment->status->value === 'PENDING'
        ) {
            $failedPayment = $payment->fail();

            $this->paymentRepository->update(
                $failedPayment
            );
        }

        return $result;
    }
}