<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Api\MpesaClient;
use BrifnetMpesa\Api\StkPushResult;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentRepository;
use BrifnetMpesa\Domain\PhoneNumber;

final class InitiatePayment
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly MpesaClient $mpesaClient,
    ) {
    }

    public function execute(
        string $reference,
        string $phone,
        int $amount,
    ): StkPushResult {
        $payment = new Payment(
            reference: new PaymentReference($reference),
            phone: new PhoneNumber($phone),
            amount: $amount,
            channel: PaymentChannel::STK,
        );

        $this->paymentRepository->save($payment);

        $result = $this->mpesaClient->initiateStkPush($payment);

        if (!$result->accepted) {
            $failedPayment = $payment->fail();

            $this->paymentRepository->update($failedPayment);

            return $result;
        }

        if (
            $result->merchantRequestId === null ||
            $result->checkoutRequestId === null
        ) {
            $failedPayment = $payment->fail();

            $this->paymentRepository->update($failedPayment);

            return new StkPushResult(
                accepted: false,
                merchantRequestId: null,
                checkoutRequestId: null,
                errorMessage: 'M-Pesa response did not contain required request identifiers.',
            );
        }

        $paymentWithIdentifiers = $payment->attachStkIdentifiers(
            merchantRequestId: $result->merchantRequestId,
            checkoutRequestId: $result->checkoutRequestId,
        );

        $this->paymentRepository->update(
            $paymentWithIdentifiers
        );

        return $result;
    }
}
