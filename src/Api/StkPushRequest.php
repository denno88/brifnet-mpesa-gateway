<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class StkPushRequest
{
    public function __construct(
        public string $businessShortCode,
        public string $password,
        public string $timestamp,
        public string $transactionType,
        public string $amount,
        public string $partyA,
        public string $partyB,
        public string $phoneNumber,
        public string $callbackUrl,
        public string $accountReference,
        public string $transactionDesc,
    ) {
    }

    public function toArray(): array
    {
        return [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $this->password,
            'Timestamp' => $this->timestamp,
            'TransactionType' => $this->transactionType,
            'Amount' => $this->amount,
            'PartyA' => $this->partyA,
            'PartyB' => $this->partyB,
            'PhoneNumber' => $this->phoneNumber,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $this->accountReference,
            'TransactionDesc' => $this->transactionDesc,
        ];
    }
}
