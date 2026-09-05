<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\MpesaClient;
use BrifnetMpesa\Api\StkPushResult;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class MpesaClientTest extends TestCase
{
    public function testFakeClientCanInitiateStkPush(): void
    {
        $client = new FakeMpesaClient();

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $result = $client->initiateStkPush($payment);

        $this->assertInstanceOf(
            MpesaClient::class,
            $client
        );

        $this->assertInstanceOf(
            StkPushResult::class,
            $result
        );

        $this->assertTrue(
            $result->accepted
        );

        $this->assertSame(
            'ws_CO_123456789',
            $result->checkoutRequestId
        );

        $this->assertSame(
            '29115-123456789',
            $result->merchantRequestId
        );

        $this->assertSame(
            $payment,
            $client->payment
        );

        $this->assertNull(
            $result->errorCode
        );

        $this->assertNull(
            $result->errorMessage
        );

    }


    public function testFakeClientCanRepresentRejectedStkPush(): void
    {
        $client = new FakeMpesaClient();

        $client->shouldAccept = false;

        $payment = new Payment(
            reference: new PaymentReference('BRIF-002'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $result = $client->initiateStkPush($payment);

        $this->assertInstanceOf(
            StkPushResult::class,
            $result
        );

        $this->assertFalse(
            $result->accepted
        );

        $this->assertNull(
            $result->merchantRequestId
        );

        $this->assertNull(
            $result->checkoutRequestId
        );

        $this->assertSame(
            '4001',
            $result->errorCode
        );

        $this->assertSame(
            'Invalid access token.',
            $result->errorMessage
        );

    }

}
