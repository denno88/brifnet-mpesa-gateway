<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use BrifnetMpesa\Domain\PhoneNumber;

final class C2BPaymentValidator
{
    public function validate(
        C2BPayment $payment,
    ): C2BValidationResult {
        try {
            new PhoneNumber($payment->phone);
        } catch (\InvalidArgumentException) {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Invalid phone number.',
            );
        }

        if ($payment->amount <= 0) {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Payment amount must be greater than zero.',
            );
        }

        if ($payment->transactionId === '') {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Transaction ID cannot be empty.',
            );
        }

        if ($payment->accountNumber === '') {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Account number cannot be empty.',
            );
        }

        if ($payment->transactionTime === '') {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Transaction time cannot be empty.',
            );
        }

        if ($payment->businessShortCode === '') {
            return new C2BValidationResult(
                accepted: false,
                errorMessage: 'Business short code cannot be empty.',
            );
        }

        return new C2BValidationResult(
            accepted: true,
        );
    }
}