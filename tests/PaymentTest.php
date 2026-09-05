<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Domain\PaymentChannel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testValidPaymentIsCreated(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $this->assertSame('BRIF-001', $payment->reference->value);
        $this->assertSame('0712345678', $payment->phone->value);
        $this->assertSame(500, $payment->amount);
        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );
    }

    public function testZeroAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment amount must be greater than zero.'
        );

        new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 0,
            channel: PaymentChannel::STK,
        );
    }

    public function testNegativeAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment amount must be greater than zero.'
        );

        new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: -100,
            channel: PaymentChannel::STK,
        );
    }
    
    public function testPendingPaymentCanBeCompleted(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $completed = $payment->complete();

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertSame(
            PaymentStatus::COMPLETED,
            $completed->status
        );
    }

    public function testPendingPaymentCanBeFailed(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $failed = $payment->fail();

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertSame(
            PaymentStatus::FAILED,
            $failed->status
        );
    }

    public function testCompletedPaymentCannotBeFailed(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $completed = $payment->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Only pending payments can be failed.'
        );

        $completed->fail();
    }

    public function testFailedPaymentCannotBeCompleted(): void
    {
        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $failed = $payment->fail();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Only pending payments can be completed.'
        );

        $failed->complete();
    }

}