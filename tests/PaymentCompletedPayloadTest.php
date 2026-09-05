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
    public function test_it_contains_the_event_identity_and_payment_data(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('PAY-123'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: $payment,
            occurredAt: new \DateTimeImmutable('2026-09-04T12:30:00+03:00'),
        );

        $builder = new PaymentCompletedPayloadBuilder();

        $payload = $builder->build($event);

        self::assertSame('evt-123', $payload['event_id']);
        self::assertSame('payment.completed', $payload['event']);
        self::assertSame(
            '2026-09-04T12:30:00+03:00',
            $payload['occurred_at'],
        );

        self::assertSame('PAY-123', $payload['data']['reference']);
        self::assertSame('0712345678', $payload['data']['phone']);
        self::assertSame(500, $payload['data']['amount']);
        self::assertSame('STK', $payload['data']['channel']);
    }

    public function test_it_can_be_serialized_to_the_webhook_json_payload(): void
    {
        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: new Payment(
                reference: new PaymentReference('PAY-123'),
                phone: new PhoneNumber('0712345678'),
                amount: 500,
                channel: PaymentChannel::STK,
            ),
            occurredAt: new DateTimeImmutable('2026-09-04T12:30:00+03:00'),
        );

        $builder = new PaymentCompletedPayloadBuilder();

        $payload = $builder->build($event);

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            '{"event_id":"evt-123","event":"payment.completed","occurred_at":"2026-09-04T12:30:00+03:00","data":{"reference":"PAY-123","phone":"0712345678","amount":500,"channel":"STK"}}',
            $json
        );
    }
}