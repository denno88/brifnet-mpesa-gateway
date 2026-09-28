<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

final class DefaultPaymentReferenceGenerator implements PaymentReferenceGenerator
{
    public function generate(): string
    {
        return 'Brif-C2B-' . strtoupper(
            bin2hex(random_bytes(6))
        );
    }
}