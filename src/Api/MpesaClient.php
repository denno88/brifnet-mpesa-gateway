<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use BrifnetMpesa\Domain\Payment;

interface MpesaClient
{
    public function initiateStkPush(Payment $payment): StkPushResult;

    public function queryStkPush(
        string $checkoutRequestId
    ): StkQueryResult;
}