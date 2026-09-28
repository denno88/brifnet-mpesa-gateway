<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\C2BPaymentParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class C2BPaymentParserTest extends TestCase
{
    private C2BPaymentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new C2BPaymentParser();
    }

    public function testItParsesValidC2BPayload(): void
    {
        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $payment = $this->parser->parse($payload);

        $this->assertSame('RKT123456', $payment->transactionId);
        $this->assertSame('0712345678', $payment->phone);
        $this->assertSame(500, $payment->amount);
        $this->assertSame('BRIF-001', $payment->accountNumber);
        $this->assertSame('20260902120000', $payment->transactionTime);
        $this->assertSame('123456', $payment->businessShortCode);
    }

    public function testItRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid C2B callback JSON.');

        $this->parser->parse('{invalid json');
    }

    public function testItRejectsMissingTransactionId(): void
    {
        $payload = json_encode([
            'MSISDN' => '0712345678',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing transaction ID.');

        $this->parser->parse($payload);
    }

    public function testItRejectsMissingPhoneNumber(): void
    {
        $payload = json_encode([
            'TransID' => 'RKT123456',
            'TransAmount' => '500',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing phone number.');

        $this->parser->parse($payload);
    }

    public function testItRejectsInvalidAmount(): void
    {
        $payload = json_encode([
            'TransID' => 'RKT123456',
            'MSISDN' => '0712345678',
            'TransAmount' => 'not-a-number',
            'BillRefNumber' => 'BRIF-001',
            'TransTime' => '20260902120000',
            'BusinessShortCode' => '123456',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Missing or invalid transaction amount.'
        );

        $this->parser->parse($payload);
    }
}