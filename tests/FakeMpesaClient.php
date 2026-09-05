<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\MpesaClient;
use BrifnetMpesa\Api\StkPushResult;
use BrifnetMpesa\Api\StkQueryResult;
use BrifnetMpesa\Domain\Payment;

final class FakeMpesaClient implements MpesaClient
{
    public ?Payment $payment = null;

    public int $callCount = 0;

    public bool $shouldAccept = true;

    public ?StkPushResult $customResult = null;

    public int $queryCallCount = 0;

    public ?string $queriedCheckoutRequestId = null;

    public ?StkQueryResult $queryResult = null;

    public function initiateStkPush(
        Payment $payment
    ): StkPushResult {
        $this->callCount++;

        $this->payment = $payment;

        if ($this->customResult !== null) {
            return $this->customResult;
        }

        if (!$this->shouldAccept) {
            return new StkPushResult(
                accepted: false,
                merchantRequestId: null,
                checkoutRequestId: null,
                errorCode: '4001',
                errorMessage: 'Invalid access token.',
            );
        }

        return new StkPushResult(
            accepted: true,
            merchantRequestId: '29115-123456789',
            checkoutRequestId: 'ws_CO_123456789',
        );
    }

    public function queryStkPush(
        string $checkoutRequestId
    ): StkQueryResult {
        $this->queryCallCount++;

        $this->queriedCheckoutRequestId = $checkoutRequestId;

        if ($this->queryResult !== null) {
            return $this->queryResult;
        }

        return new StkQueryResult(
            successful: true,
            responseCode: '0',
            responseDescription:
                'The service request is processed successfully.',
            merchantRequestId: '29115-123456789',
            checkoutRequestId: $checkoutRequestId,
            resultCode: '0',
            resultDescription:
                'The service request is processed successfully.',
        );
    }
}