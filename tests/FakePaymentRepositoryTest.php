<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class FakePaymentRepositoryTest extends TestCase
{
    public function testPaymentCanBeFoundByCheckoutRequestId(): void
    {
        $repository = new FakePaymentRepository();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $payment = $payment->attachStkIdentifiers(
            merchantRequestId: '29115-123456789',
            checkoutRequestId: 'ws_CO_123456789',
        );

        $repository->save($payment);

        $found = $repository->findByCheckoutRequestId(
            'ws_CO_123456789'
        );

        $this->assertSame(
            $payment,
            $found
        );
    }

    public function testUnknownCheckoutRequestIdReturnsNull(): void
    {
        $repository = new FakePaymentRepository();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $payment = $payment->attachStkIdentifiers(
            merchantRequestId: '29115-123456789',
            checkoutRequestId: 'ws_CO_123456789',
        );

        $repository->save($payment);

        $found = $repository->findByCheckoutRequestId(
            'unknown-checkout-id'
        );

        $this->assertNull($found);
    }

    public function testLookupReturnsNullWhenRepositoryIsEmpty(): void
    {
        $repository = new FakePaymentRepository();

        $found = $repository->findByCheckoutRequestId(
            'ws_CO_123456789'
        );

        $this->assertNull($found);
    }
}
