<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\C2BPayment;
use BrifnetMpesa\Api\C2BPaymentValidator;
use PHPUnit\Framework\TestCase;

final class C2BPaymentValidatorTest extends TestCase
{
    private C2BPaymentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new C2BPaymentValidator();
    }

    public function testItAcceptsValidPayment(): void
    {
        $payment = new C2BPayment(
            transactionId: 'RKT123456',
            phone: '0712345678',
            amount: 500,
            billReferenceNumber: 'BRIF-001',
            transactionTime: '20260902120000',
            businessShortCode: '123456',
        );

        $result = $this->validator->validate($payment);

        $this->assertTrue($result->accepted);
        $this->assertNull($result->errorMessage);
    }

    public function testItRejectsInvalidPhoneNumber(): void
    {
        $payment = new C2BPayment(
            transactionId: 'RKT123456',
            phone: '12345',
            amount: 500,
            billReferenceNumber: 'BRIF-001',
            transactionTime: '20260902120000',
            businessShortCode: '123456',
        );

        $result = $this->validator->validate($payment);

        $this->assertFalse($result->accepted);
        $this->assertSame(
            'Invalid phone number.',
            $result->errorMessage
        );
    }

    public function testItRejectsNonPositiveAmount(): void
    {
        $payment = new C2BPayment(
            transactionId: 'RKT123456',
            phone: '0712345678',
            amount: 0,
            billReferenceNumber: 'BRIF-001',
            transactionTime: '20260902120000',
            businessShortCode: '123456',
        );

        $result = $this->validator->validate($payment);

        $this->assertFalse($result->accepted);
        $this->assertSame(
            'Payment amount must be greater than zero.',
            $result->errorMessage
        );
    }

    public function testItRejectsEmptyTransactionId(): void
    {
        $payment = new C2BPayment(
            transactionId: '',
            phone: '0712345678',
            amount: 500,
            billReferenceNumber: 'BRIF-001',
            transactionTime: '20260902120000',
            businessShortCode: '123456',
        );

        $result = $this->validator->validate($payment);

        $this->assertFalse($result->accepted);
        $this->assertSame(
            'Transaction ID cannot be empty.',
            $result->errorMessage
        );
    }

    public function testItRejectsEmptyBillReferenceNumber(): void
    {
        $payment = new C2BPayment(
            transactionId: 'RKT123456',
            phone: '0712345678',
            amount: 500,
            billReferenceNumber: '',
            transactionTime: '20260902120000',
            businessShortCode: '123456',
        );

        $result = $this->validator->validate($payment);

        $this->assertFalse($result->accepted);
        $this->assertSame(
            'Bill reference number cannot be empty.',
            $result->errorMessage
        );
    }
}