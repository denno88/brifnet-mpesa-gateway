<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkPushRequest;
use BrifnetMpesa\Api\StkPushRequestBuilder;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class StkPushRequestBuilderTest extends TestCase
{
    public function testPaymentIsConvertedIntoStkPushRequest(): void
    {
        $builder = new StkPushRequestBuilder(
            businessShortCode: '174379',
            transactionType: 'CustomerPayBillOnline',
            callbackUrl: 'https://example.com/callback',
            transactionDesc: 'BrifNet payment',
        );

        $payment = new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $request = $builder->build(
            payment: $payment,
            password: 'encoded-password',
            timestamp: '20260902130000',
        );

        $this->assertInstanceOf(
            StkPushRequest::class,
            $request
        );

        $this->assertSame(
            [
                'BusinessShortCode' => '174379',
                'Password' => 'encoded-password',
                'Timestamp' => '20260902130000',
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => '500',
                'PartyA' => '254712345678',
                'PartyB' => '174379',
                'PhoneNumber' => '254712345678',
                'CallBackURL' => 'https://example.com/callback',
                'AccountReference' => 'BRIF-001',
                'TransactionDesc' => 'BrifNet payment',
            ],
            $request->toArray()
        );
    }
}
