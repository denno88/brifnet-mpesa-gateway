<?php

declare(strict_types=1);

use BrifnetMpesa\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function test_it_generates_an_hmac_sha256_signature_using_timestamp_and_payload(): void
    {
        $signature = new WebhookSignature();

        $payload = '{"event":"payment.completed","reference":"PAY-123","amount":500}';
        $secret = 'test-secret';
        $timestamp = '2026-10-01T12:30:00Z';

        $result = $signature->generate(
            payload: $payload,
            secret: $secret,
            timestamp: $timestamp,
        );

        $this->assertSame(
            hash_hmac(
                'sha256',
                $timestamp . '.' . $payload,
                $secret,
            ),
            $result
        );
    }

    public function test_different_timestamps_produce_different_signatures(): void
    {
        $signature = new WebhookSignature();

        $payload = '{"event":"payment.completed","reference":"PAY-123"}';
        $secret = 'test-secret';

        $first = $signature->generate(
            payload: $payload,
            secret: $secret,
            timestamp: '2026-10-01T12:30:00Z',
        );

        $second = $signature->generate(
            payload: $payload,
            secret: $secret,
            timestamp: '2026-10-01T12:31:00Z',
        );

        $this->assertNotSame($first, $second);
    }

    public function test_payload_is_part_of_the_signature(): void
    {
        $signature = new WebhookSignature();

        $secret = 'test-secret';
        $timestamp = '2026-10-01T12:30:00Z';

        $first = $signature->generate(
            payload: '{"reference":"PAY-123"}',
            secret: $secret,
            timestamp: $timestamp,
        );

        $second = $signature->generate(
            payload: '{"reference":"PAY-456"}',
            secret: $secret,
            timestamp: $timestamp,
        );

        $this->assertNotSame($first, $second);
    }
}