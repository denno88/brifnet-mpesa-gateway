<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

final class WebhookSignature
{
    public function generate(
        string $payload,
        string $secret,
    ): string {
        return hash_hmac(
            'sha256',
            $payload,
            $secret,
        );
    }
}