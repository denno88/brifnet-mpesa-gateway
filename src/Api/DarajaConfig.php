<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final readonly class DarajaConfig
{
    public function __construct(
        public string $consumerKey,
        public string $consumerSecret,
        public string $businessShortCode,
        public string $passkey,
        public string $authUrl,
        public string $stkPushUrl,
        public string $stkQueryUrl,
        public string $callbackUrl,
        public string $transactionType = 'CustomerPayBillOnline',
        public string $transactionDescription = 'BrifNet payment',
    ) {
    }
}
