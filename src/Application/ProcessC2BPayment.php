<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Api\C2BPaymentParser;
use BrifnetMpesa\Api\C2BPaymentValidator;
use BrifnetMpesa\Domain\DuplicatePaymentException;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentRepository;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;

final class ProcessC2BPayment
{
    public function __construct(
        private readonly C2BPaymentParser $parser,
        private readonly C2BPaymentValidator $validator,
        private readonly PaymentRepository $paymentRepository,
        private readonly CompletePayment $completePayment,
        private readonly PaymentReferenceGenerator $referenceGenerator,
    ) {
    }

    public function execute(string $payload): Payment
    {
        $c2bPayment = $this->parser->parse($payload);

        $validation = $this->validator->validate(
            $c2bPayment
        );

        if (!$validation->accepted) {
            throw new \InvalidArgumentException(
                $validation->errorMessage ?? 'Invalid C2B payment.'
            );
        }

        /*
         * TransID is the provider transaction ID and therefore
         * the idempotency key for C2B payments.
         */
        $existingPayment = $this->paymentRepository
            ->findByTransactionId(
                $c2bPayment->transactionId
            );

        if ($existingPayment !== null) {
            return $existingPayment;
        }

        $payment = new Payment(
            reference: new PaymentReference(
                $this->referenceGenerator->generate()
            ),
            phone: new PhoneNumber(
                $c2bPayment->phone
            ),
            amount: $c2bPayment->amount,
            channel: PaymentChannel::C2B,
            status: PaymentStatus::COMPLETED,
            transactionId: $c2bPayment->transactionId,
            accountNumber: $c2bPayment->accountNumber,
        );

        try {
            $this->completePayment->executeNew($payment);
        } catch (DuplicatePaymentException) {
            /*
             * Another request may have processed the same TransID
             * concurrently. Return the payment that won the race.
             */
            $existingPayment = $this->paymentRepository
                ->findByTransactionId(
                    $c2bPayment->transactionId
                );

            if ($existingPayment !== null) {
                return $existingPayment;
            }

            throw new \RuntimeException(
                'Payment was reported as duplicate, but the existing payment could not be found.'
            );
        }

        return $payment;
    }
}