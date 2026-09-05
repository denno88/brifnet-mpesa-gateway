<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkPushRequest;
use PHPUnit\Framework\TestCase;

final class StkPushRequestTest extends TestCase
{
    public function testRequestCanBeConvertedToDarajaPayload(): void
    {
        $request = new StkPushRequest(
            businessShortCode: '174379',
            password: 'encoded-password',
            timestamp: '20260902130000',
            transactionType: 'CustomerPayBillOnline',
            amount: '500',
            partyA: '0712345678',
            partyB: '174379',
            phoneNumber: '0712345678',
            callbackUrl: 'https://example.com/callback',
            accountReference: 'BRIF-001',
            transactionDesc: 'BrifNet payment',
        );

        $this->assertSame(
            [
                'BusinessShortCode' => '174379',
                'Password' => 'encoded-password',
                'Timestamp' => '20260902130000',
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => '500',
                'PartyA' => '0712345678',
                'PartyB' => '174379',
                'PhoneNumber' => '0712345678',
                'CallBackURL' => 'https://example.com/callback',
                'AccountReference' => 'BRIF-001',
                'TransactionDesc' => 'BrifNet payment',
            ],
            $request->toArray()
        );
    }
}
