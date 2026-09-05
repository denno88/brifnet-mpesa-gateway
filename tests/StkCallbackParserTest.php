<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Api\StkCallbackResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StkCallbackParserTest extends TestCase
{
    public function testSuccessfulCallbackIsParsed(): void
    {
        $json = json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-123456789',
                    'CheckoutRequestID' => 'ws_CO_123456789',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            [
                                'Name' => 'Amount',
                                'Value' => 500,
                            ],
                            [
                                'Name' => 'MpesaReceiptNumber',
                                'Value' => 'QAB123XYZ',
                            ],
                            [
                                'Name' => 'TransactionDate',
                                'Value' => 20260831120000,
                            ],
                            [
                                'Name' => 'PhoneNumber',
                                'Value' => '0712345678',
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $parser = new StkCallbackParser();

        $result = $parser->parse($json);

        $this->assertInstanceOf(
            StkCallbackResult::class,
            $result
        );

        $this->assertSame(
            '29115-123456789',
            $result->merchantRequestId
        );

        $this->assertSame(
            'ws_CO_123456789',
            $result->checkoutRequestId
        );

        $this->assertSame(0, $result->resultCode);

        $this->assertTrue($result->isSuccessful());

        $this->assertSame(
            'The service request is processed successfully.',
            $result->resultDescription
        );

        $this->assertSame(500, $result->amount);

        $this->assertSame(
            'QAB123XYZ',
            $result->receipt
        );

        $this->assertSame(
            '0712345678',
            $result->phone
        );
    }

    public function testFailedCallbackCanBeParsedWithoutMetadata(): void
    {
        $json = json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-123456789',
                    'CheckoutRequestID' => 'ws_CO_123456789',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request canceled by user.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $parser = new StkCallbackParser();

        $result = $parser->parse($json);

        $this->assertSame(1032, $result->resultCode);
        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->amount);
        $this->assertNull($result->receipt);
        $this->assertNull($result->phone);
    }

    public function testInvalidJsonIsRejected(): void
    {
        $parser = new StkCallbackParser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid callback JSON.'
        );

        $parser->parse('{invalid-json');
    }

    public function testMissingCallbackBodyIsRejected(): void
    {
        $parser = new StkCallbackParser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid STK callback payload.'
        );

        $parser->parse(
            json_encode([
                'Body' => [],
            ], JSON_THROW_ON_ERROR)
        );
    }

    public function testMissingCheckoutRequestIdIsRejected(): void
    {
        $parser = new StkCallbackParser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Missing CheckoutRequestID.'
        );

        $parser->parse(
            json_encode([
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => '29115-123456789',
                        'ResultCode' => 0,
                        'ResultDesc' => 'Success',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
    }
}
