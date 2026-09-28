<?php

declare(strict_types=1);

use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PaymentCompletedPayloadTest extends TestCase
{
    public function test_it_builds_the_stk_webhook_payload(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('PAY-123'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            merchantRequestId: 'ws_CO_12345',
            checkoutRequestId: 'ws_CO_67890',
            transactionId: 'ABC123XYZ',
        );

        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: $payment,
            occurredAt: new \DateTimeImmutable(
                '2026-09-04T12:30:00+03:00'
            ),
        );

        $builder = new PaymentCompletedPayloadBuilder();

        $payload = $builder->build($event);

        self::assertSame('evt-123', $payload['event_id']);
        self::assertSame('payment.completed', $payload['event']);
        self::assertSame(
            '2026-09-04T12:30:00+03:00',
            $payload['occurred_at'],
        );

        self::assertSame(
            'PAY-123',
            $payload['data']['reference']
        );

        self::assertSame(
            '0712345678',
            $payload['data']['phone']
        );

        self::assertSame(
            500,
            $payload['data']['amount']
        );

        self::assertSame(
            'STK',
            $payload['data']['channel']
        );

        self::assertSame(
            'ws_CO_67890',
            $payload['data']['provider_reference']
        );

        self::assertSame(
            'ABC123XYZ',
            $payload['data']['provider_transaction_id']
        );
    }

    public function test_it_builds_the_c2b_webhook_payload(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('PAY-C2B-A81F92B3C410'),
            phone: new PhoneNumber('0712345678'),
            amount: 349,
            channel: PaymentChannel::C2B,
            transactionId: 'RKT123456',
            accountNumber: 'KAM100',
        );

        $event = new PaymentCompleted(
            eventId: 'evt-456',
            payment: $payment,
            occurredAt: new \DateTimeImmutable(
                '2026-09-04T12:30:00+03:00'
            ),
        );

        $builder = new PaymentCompletedPayloadBuilder();

        $payload = $builder->build($event);

        self::assertSame('evt-456', $payload['event_id']);
        self::assertSame('payment.completed', $payload['event']);
        self::assertSame(
            '2026-09-04T12:30:00+03:00',
            $payload['occurred_at'],
        );

        self::assertSame(
            'KAM100',
            $payload['data']['account_number']
        );

        self::assertSame(
            '0712345678',
            $payload['data']['phone']
        );

        self::assertSame(
            349,
            $payload['data']['amount']
        );

        self::assertSame(
            'C2B',
            $payload['data']['channel']
        );

        self::assertSame(
            'RKT123456',
            $payload['data']['provider_transaction_id']
        );

        self::assertArrayNotHasKey(
            'reference',
            $payload['data']
        );

        self::assertArrayNotHasKey(
            'provider_reference',
            $payload['data']
        );
    }

    public function test_it_can_serialize_the_stk_webhook_json_payload(): void
    {
        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: new Payment(
                reference: new PaymentReference('PAY-123'),
                phone: new PhoneNumber('0712345678'),
                amount: 500,
                channel: PaymentChannel::STK,
                merchantRequestId: 'ws_CO_12345',
                checkoutRequestId: 'ws_CO_67890',
                transactionId: 'ABC123XYZ',
            ),
            occurredAt: new \DateTimeImmutable(
                '2026-09-04T12:30:00+03:00'
            ),
        );

        $builder = new PaymentCompletedPayloadBuilder();

        $payload = $builder->build($event);

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            '{"event_id":"evt-123","event":"payment.completed","occurred_at":"2026-09-04T12:30:00+03:00","data":{"reference":"PAY-123","phone":"0712345678","amount":500,"channel":"STK","provider_reference":"ws_CO_67890","provider_transaction_id":"ABC123XYZ"}}',
            $json
        );
    }
}