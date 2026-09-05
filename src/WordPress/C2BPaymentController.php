<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Application\ProcessC2BPayment;
use InvalidArgumentException;
use Throwable;

final class C2BPaymentController
{
    public function __construct(
        private readonly ProcessC2BPayment $processor,
    ) {
    }

    public function handle(
        string $payload,
    ): RestResponse {
        try {
            $payment = $this->processor->execute($payload);

            return new RestResponse(
                statusCode: 200,
                body: [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                    'TransactionId' => $payment->transactionId,
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return new RestResponse(
                statusCode: 200,
                body: [
                    'ResultCode' => 1,
                    'ResultDesc' => $exception->getMessage(),
                ],
            );
        } catch (Throwable) {
            return new RestResponse(
                statusCode: 500,
                body: [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Internal server error.',
                ],
            );
        }
    }
}