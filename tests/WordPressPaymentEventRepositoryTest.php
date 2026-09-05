<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\WordPress\WordPressPaymentEventRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WordPressPaymentEventRepositoryTest extends TestCase
{
    public function testPaymentCompletedEventCanBeSaved(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentEventRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::COMPLETED,
            transactionId: 'RKT123456',
        );

        $event = new PaymentCompleted(
            eventId: 'event-2001',
            payment: $payment,
            occurredAt: new \DateTimeImmutable('2026-09-03 10:00:00'),
        );

        $repository->save($event);

        $this->assertCount(1, $database->inserted);

        $insert = $database->inserted[0];

        $this->assertSame(
            'wp_brifnet_mpesa_payment_events',
            $insert['table']
        );

        $this->assertSame(
            'event-2001',
            $insert['data']['event_id']
        );

        $this->assertSame(
            'payment.completed',
            $insert['data']['event_name']
        );

        $this->assertSame(
            'BRIF-2001',
            $insert['data']['payment_reference']
        );

        $this->assertSame(
            '2026-09-03 10:00:00',
            $insert['data']['occurred_at']
        );

        $this->assertArrayHasKey(
            'payload',
            $insert['data']
        );

        $payload = json_decode(
            $insert['data']['payload'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            [
                'reference' => 'BRIF-2001',
                'phone' => '0712345678',
                'amount' => 500,
                'channel' => 'STK',
                'status' => 'COMPLETED',
                'merchant_request_id' => null,
                'checkout_request_id' => null,
                'transaction_id' => 'RKT123456',
            ],
            $payload
        );

        $this->assertArrayHasKey(
            'created_at',
            $insert['data']
        );
    }

    public function testSaveFailureThrowsException(): void
    {
        $database = new FakeDatabase();
        $database->insertShouldFail = true;

        $repository = new WordPressPaymentEventRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2002'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::COMPLETED,
        );

        $event = new PaymentCompleted(
            eventId: 'event-2002',
            payment: $payment,
            occurredAt: new \DateTimeImmutable('2026-09-03 10:00:00'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to save payment event.'
        );

        $repository->save($event);
    }

    public function testPaymentCompletedEventCanBeFoundByEventId(): void
    {
        $database = new FakeDatabase();

        $payload = json_encode(
            [
                'reference' => 'BRIF-2003',
                'phone' => '0712345678',
                'amount' => 750,
                'channel' => 'STK',
                'status' => 'COMPLETED',
                'merchant_request_id' => '29115-123456789',
                'checkout_request_id' => 'ws_CO_123456789',
                'transaction_id' => 'RKT123456',
            ],
            JSON_THROW_ON_ERROR
        );

        $database->rows[] = (object) [
            'event_id' => 'event-2003',
            'event_name' => 'payment.completed',
            'payment_reference' => 'BRIF-2003',
            'payload' => $payload,
            'occurred_at' => '2026-09-03 11:00:00',
        ];

        $repository = new WordPressPaymentEventRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $event = $repository->findByEventId('event-2003');

        $this->assertNotNull($event);

        $this->assertSame(
            'event-2003',
            $event->eventId
        );

        $this->assertSame(
            'payment.completed',
            $event->name()
        );

        $this->assertSame(
            'BRIF-2003',
            $event->payment->reference->value
        );

        $this->assertSame(
            '0712345678',
            $event->payment->phone->value
        );

        $this->assertSame(
            750,
            $event->payment->amount
        );

        $this->assertSame(
            PaymentChannel::STK,
            $event->payment->channel
        );

        $this->assertSame(
            PaymentStatus::COMPLETED,
            $event->payment->status
        );

        $this->assertSame(
            '29115-123456789',
            $event->payment->merchantRequestId
        );

        $this->assertSame(
            'ws_CO_123456789',
            $event->payment->checkoutRequestId
        );

        $this->assertSame(
            'RKT123456',
            $event->payment->transactionId
        );

        $this->assertSame(
            '2026-09-03 11:00:00',
            $event->occurredAt->format('Y-m-d H:i:s')
        );
    }

    public function testUnknownEventIdReturnsNull(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentEventRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $event = $repository->findByEventId('unknown-event');

        $this->assertNull($event);
    }

    public function testDuplicateEventThrowsException(): void
    {
        $database = new FakeDatabase();
        $database->insertShouldFailAsDuplicate = true;

        $repository = new WordPressPaymentEventRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-2004'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
            status: PaymentStatus::COMPLETED,
        );

        $event = new PaymentCompleted(
            eventId: 'event-2004',
            payment: $payment,
            occurredAt: new \DateTimeImmutable('2026-09-03 10:00:00'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Payment event already exists.'
        );

        $repository->save($event);
    }
}