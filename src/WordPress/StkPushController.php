<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Application\InitiatePayment;
use RuntimeException;

final class StkPushController
{
    public function __construct(
        private readonly InitiatePayment $initiatePayment,
    ) {
    }

    public function handle(array $data): RestResponse
    {
        try {
            $reference = $data['reference'] ?? null;
            $phone = $data['phone'] ?? null;
            $amount = $data['amount'] ?? null;

            if (
                !is_string($reference) ||
                !is_string($phone) ||
                !is_int($amount)
            ) {
                return new RestResponse(
                    statusCode: 400,
                    body: [
                        'message' => 'Invalid request.',
                    ],
                );
            }

            $result = $this->initiatePayment->execute(
                reference: $reference,
                phone: $phone,
                amount: $amount,
            );

            if (!$result->accepted) {
                return new RestResponse(
                    statusCode: 502,
                    body: [
                        'message' => 'M-Pesa rejected the STK Push.',
                        'error' => $result->errorMessage,
                    ],
                );
            }

            return new RestResponse(
                statusCode: 200,
                body: [
                    'message' => 'STK Push initiated.',
                    'merchant_request_id' => $result->merchantRequestId,
                    'checkout_request_id' => $result->checkoutRequestId,
                ],
            );
        } catch (\Throwable $exception) {
            return new RestResponse(
                statusCode: $exception->getCode(),
                body: [
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}