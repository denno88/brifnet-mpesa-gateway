<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use BrifnetMpesa\Domain\Payment;

final class StkPushRequestBuilder
{
    public function __construct(
        private readonly string $businessShortCode,
        private readonly string $transactionType,
        private readonly string $callbackUrl,
        private readonly string $transactionDesc,
    ) {
    }

    public function build(
        Payment $payment,
        string $password,
        string $timestamp,
    ): StkPushRequest {
        $darajaPhoneNumber = $this->toDarajaPhoneNumber(
            $payment->phone->value
        );

        return new StkPushRequest(
            businessShortCode: $this->businessShortCode,
            password: $password,
            timestamp: $timestamp,
            transactionType: $this->transactionType,
            amount: (string) $payment->amount,
            partyA: $darajaPhoneNumber,
            partyB: $this->businessShortCode,
            phoneNumber: $darajaPhoneNumber,
            callbackUrl: $this->callbackUrl,
            accountReference: $payment->reference->value,
            transactionDesc: $this->transactionDesc,
        );
    }

    private function toDarajaPhoneNumber(string $phone): string
    {
        return '254' . substr($phone, 1);
    }
}