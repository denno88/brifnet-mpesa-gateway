<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\InitiatePayment;
use BrifnetMpesa\Domain\PaymentChannel;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Api\StkPushResult;
use BrifnetMpesa\Domain\PaymentStatus;

final class InitiatePaymentTest extends TestCase
{
    public function testPaymentIsCreatedAndSentToMpesaClient(): void
    {
        $client = new FakeMpesaClient();

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $result = $service->execute(
            reference: 'BRIF-001',
            phone: '0712345678',
            amount: 500,
        );

        $this->assertInstanceOf(
            StkPushResult::class,
            $result
        );

        $this->assertTrue($result->accepted);

        $this->assertSame(
            'ws_CO_123456789',
            $result->checkoutRequestId
        );

        $this->assertSame(
            '29115-123456789',
            $result->merchantRequestId
        );

        $this->assertSame(1, $repository->saveCount);
        $this->assertSame(1, $repository->updateCount);
        $this->assertNotNull($repository->payment);

        $this->assertNotNull($client->payment);

        $this->assertSame(
            'BRIF-001',
            $client->payment->reference->value
        );

        $this->assertSame(
            '0712345678',
            $client->payment->phone->value
        );

        $this->assertSame(
            500,
            $client->payment->amount
        );

        $this->assertSame(
            PaymentChannel::STK,
            $client->payment->channel
        );

        $this->assertNull(
            $client->payment->checkoutRequestId
        );

        $this->assertNull(
            $client->payment->merchantRequestId
        );


    }

    public function testInvalidPhoneIsRejectedBeforeMpesaClientIsCalled(): void
    {
        $client = new FakeMpesaClient();

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Phone number must be a valid Kenyan mobile number.'
        );

        try {
            $service->execute(
                reference: 'BRIF-001',
                phone: '1234567890',
                amount: 500,
            );
        } finally {
            $this->assertSame(0, $repository->saveCount);
            $this->assertSame(0, $client->callCount);
        }
    }

    public function testEmptyReferenceIsRejectedBeforeMpesaClientIsCalled(): void
    {
        $client = new FakeMpesaClient();

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment reference cannot be empty.'
        );

        try {
            $service->execute(
                reference: '',
                phone: '0712345678',
                amount: 500,
            );
        } finally {
            $this->assertSame(0, $repository->saveCount);
            $this->assertSame(0, $client->callCount);
        }
    }

    public function testInvalidAmountIsRejectedBeforeMpesaClientIsCalled(): void
    {
        $client = new FakeMpesaClient();

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment amount must be greater than zero.'
        );

        try {
            $service->execute(
                reference: 'BRIF-001',
                phone: '0712345678',
                amount: 0,
            );
        } finally {
            $this->assertSame(0, $repository->saveCount);
            $this->assertSame(0, $client->callCount);
        }
    }

    public function testRejectedStkPushFailsThePayment(): void
    {
        $client = new FakeMpesaClient();

        $client->shouldAccept = false;

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $result = $service->execute(
            reference: 'BRIF-002',
            phone: '0712345678',
            amount: 500,
        );

        $this->assertFalse($result->accepted);

        $this->assertSame(1, $repository->saveCount);
        $this->assertSame(1, $repository->updateCount);

        $this->assertNotNull($repository->payment);

        $this->assertSame(
            PaymentStatus::FAILED,
            $repository->payment->status
        );

        $this->assertSame(
            'BRIF-002',
            $repository->payment->reference->value
        );

        $this->assertSame(
            500,
            $repository->payment->amount
        );

        $this->assertSame(
            1,
            $client->callCount
        );
    }

    public function testMpesaClientIsNotCalledWhenPaymentCannotBeSaved(): void
    {
        $client = new FakeMpesaClient();

        $repository = new FakePaymentRepository();
        $repository->saveShouldFail = true;

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to save payment.');

        try {
            $service->execute(
                reference: 'BRIF-003',
                phone: '0712345678',
                amount: 500,
            );
        } finally {
            $this->assertSame(1, $repository->saveCount);
            $this->assertSame(0, $client->callCount);
        }
    }


    public function testAcceptedStkPushWithoutRequestIdentifiersFailsThePayment(): void
    {
        $client = new FakeMpesaClient();

        $client->customResult = new StkPushResult(
            accepted: true,
            merchantRequestId: null,
            checkoutRequestId: null,
        );

        $repository = new FakePaymentRepository();

        $service = new InitiatePayment(
            $repository,
            $client
        );

        $result = $service->execute(
            reference: 'BRIF-004',
            phone: '0712345678',
            amount: 500,
        );

        $this->assertFalse($result->accepted);

        $this->assertSame(
            PaymentStatus::FAILED,
            $repository->payment->status
        );

        $this->assertSame(1, $repository->saveCount);
        $this->assertSame(1, $repository->updateCount);
    }



}
