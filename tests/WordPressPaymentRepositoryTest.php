<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\WordPress\WordPressPaymentRepository;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Api\DateTimeProvider;
use RuntimeException;

final class WordPressPaymentRepositoryTest extends TestCase
{
    public function testPaymentCanBeSaved(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-1001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $repository->save($payment);

        $this->assertCount(1, $database->inserted);

        $insert = $database->inserted[0];

        $this->assertSame(
            'wp_brifnet_mpesa_transactions',
            $insert['table']
        );

        $this->assertSame(
            'BRIF-1001',
            $insert['data']['reference']
        );

        $this->assertSame(
            '0712345678',
            $insert['data']['phone']
        );

        $this->assertSame(
            500,
            $insert['data']['amount']
        );

        $this->assertSame(
            PaymentStatus::PENDING->value,
            $insert['data']['status']
        );

        $this->assertSame(
            PaymentChannel::STK->value,
            $insert['data']['payment_channel']
        );
    }

    public function testSaveFailureThrowsException(): void
    {
        $database = new FakeDatabase();
        $database->insertShouldFail = true;

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-1002'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to save payment.');

        $repository->save($payment);
    }

    public function testPaymentCanBeUpdated(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-1003'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $completed = $payment->complete();

        $repository->update($completed);

        $this->assertCount(1, $database->updated);

        $update = $database->updated[0];

        $this->assertSame(
            PaymentStatus::COMPLETED->value,
            $update['data']['status']
        );

        $this->assertSame(
            'BRIF-1003',
            $update['where']['reference']
        );
    }

    public function testUpdateFailureThrowsException(): void
    {
        $database = new FakeDatabase();
        $database->updateShouldFail = true;

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-1004'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $completed = $payment->complete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update payment.');

        $repository->update($completed);
    }


    public function testPaymentCanBeFoundByCheckoutRequestId(): void
    {
        $database = new FakeDatabase();

        $database->rows[] = (object) [
            'reference' => 'BRIF-1005',
            'phone' => '0712345678',
            'amount' => '500',
            'status' => 'PENDING',
            'payment_channel' => 'STK',
            'merchant_request_id' => '29115-123456789',
            'checkout_request_id' => 'ws_CO_123456789',
        ];

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = $repository->findByCheckoutRequestId(
            'ws_CO_123456789'
        );

        $this->assertNotNull($payment);

        $this->assertSame(
            'BRIF-1005',
            $payment->reference->value
        );

        $this->assertSame(
            '0712345678',
            $payment->phone->value
        );

        $this->assertSame(
            500,
            $payment->amount
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertSame(
            PaymentChannel::STK,
            $payment->channel
        );

        $this->assertSame(
            '29115-123456789',
            $payment->merchantRequestId
        );

        $this->assertSame(
            'ws_CO_123456789',
            $payment->checkoutRequestId
        );
    }

    public function testUnknownCheckoutRequestIdReturnsNull(): void
    {
        $database = new FakeDatabase();

        $database->rows[] = (object) [
            'reference' => 'BRIF-1006',
            'phone' => '0712345678',
            'amount' => '500',
            'status' => 'PENDING',
            'payment_channel' => 'STK',
            'merchant_request_id' => '29115-123456789',
            'checkout_request_id' => 'ws_CO_123456789',
        ];

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = $repository->findByCheckoutRequestId(
            'unknown-checkout-id'
        );

        $this->assertNull($payment);
    }

    public function testItFindsPaymentByTransactionId(): void
    {
        $database = new FakeDatabase();

        $database->rows[] = (object) [
            'reference' => 'BRIF-001',
            'phone' => '0712345678',
            'amount' => '500',
            'status' => 'COMPLETED',
            'payment_channel' => 'C2B',
            'merchant_request_id' => null,
            'checkout_request_id' => null,
            'trans_id' => 'RKT123456',
        ];

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = $repository->findByTransactionId(
            'RKT123456'
        );

        $this->assertNotNull($payment);
        $this->assertSame(
            'RKT123456',
            $payment->transactionId
        );
        $this->assertSame(
            'C2B',
            $payment->channel->value
        );
        $this->assertSame(
            'COMPLETED',
            $payment->status->value
        );
    }

    public function testItReturnsNullForUnknownTransactionId(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = $repository->findByTransactionId(
            'UNKNOWN123'
        );

        $this->assertNull($payment);
    }

    public function testItSavesTransactionId(): void
    {
        $database = new FakeDatabase();

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::C2B,
            status: PaymentStatus::COMPLETED,
            transactionId: 'RKT123456',
        );

        $repository->save($payment);

        $this->assertSame(
            'RKT123456',
            $database->inserted[0]['data']['trans_id']
        );
    }

    public function testItThrowsDuplicatePaymentException(): void
    {
        $database = new FakeDatabase();

        $database->insertShouldFailAsDuplicate = true;

        $repository = new WordPressPaymentRepository(
            database: $database,
            tableName: 'wp_brifnet_mpesa_transactions',
            dateTimeProvider: new FakeDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::C2B,
            status: PaymentStatus::COMPLETED,
            transactionId: 'RKT123456',
        );

        $this->expectException(
            \BrifnetMpesa\Domain\DuplicatePaymentException::class
        );

        $repository->save($payment);
    }

}

final class FakeDateTimeProvider implements DateTimeProvider
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            '2026-09-04 15:00:00',
            new \DateTimeZone('Africa/Nairobi')
        );
    }
}
