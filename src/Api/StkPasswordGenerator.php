<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class StkPasswordGenerator
{
    public function generate(
        string $businessShortCode,
        string $passkey,
        string $timestamp,
    ): string {
        return base64_encode(
            $businessShortCode . $passkey . $timestamp
        );
    }
}
