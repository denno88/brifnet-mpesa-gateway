<?php

declare(strict_types=1);

use BrifnetMpesa\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function test_it_generates_an_hmac_sha256_signature_for_a_payload(): void
    {
        $signature = new WebhookSignature();

        $payload = '{"event":"payment.completed","reference":"PAY-123","amount":500}';
        $secret = 'test-secret';

        $result = $signature->generate(
            $payload,
            $secret,
        );

        $this->assertSame(
            hash_hmac('sha256', $payload, $secret),
            $result
        );
    }
}