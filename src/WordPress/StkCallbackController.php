<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Application\ProcessStkCallback;
use InvalidArgumentException;
use Throwable;

final class StkCallbackController
{
    public function __construct(
        private readonly ProcessStkCallback $processor,
    ) {
    }

    public function handle(string $payload): RestResponse
    {
        try {
            $this->processor->execute($payload);

            return new RestResponse(
                statusCode: 200,
                body: [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                ],
            );
        } catch (InvalidArgumentException) {
            /*
             * The callback is malformed or refers to a payment
             * that cannot be processed as supplied.
             *
             * We acknowledge the HTTP request but tell M-Pesa
             * that the callback was not accepted.
             */
            return new RestResponse(
                statusCode: 200,
                body: [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Callback could not be processed.',
                ],
            );
        } catch (Throwable) {
            /*
             * Unexpected/internal failure.
             *
             * Returning 500 allows the provider/request infrastructure
             * to treat this as a server-side failure rather than a
             * permanently invalid callback.
             */
            return new RestResponse(
                statusCode: 500,
                body: [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Internal callback processing error.',
                ],
            );
        }
    }
}