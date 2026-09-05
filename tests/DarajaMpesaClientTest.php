<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\DarajaMpesaClient;
use BrifnetMpesa\Api\StkPasswordGenerator;
use BrifnetMpesa\Api\StkPushRequestBuilder;
use BrifnetMpesa\Api\StkPushResult;
use BrifnetMpesa\Api\HttpResponse;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PhoneNumber;
use PHPUnit\Framework\TestCase;
use BrifnetMpesa\Api\AccessTokenProvider;
use BrifnetMpesa\Api\AccessTokenResult;
use BrifnetMpesa\Api\StkQueryResult;



final class DarajaMpesaClientTest extends TestCase
{
    private function createPayment(): Payment
    {
        return new Payment(
            reference: new PaymentReference('BRIF-001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );
    }


    private function createClient(
        FakeHttpClient $httpClient,
        FakeClock $clock,
        ?AccessTokenProvider $accessTokenProvider = null,
    ): DarajaMpesaClient {
        $accessTokenProvider ??= $this->createSuccessfulAccessTokenProvider();

        return new DarajaMpesaClient(
            httpClient: $httpClient,
            accessTokenProvider: $accessTokenProvider,
            clock: $clock,
            passwordGenerator: new StkPasswordGenerator(),
            requestBuilder: new StkPushRequestBuilder(
                businessShortCode: '174379',
                transactionType: 'CustomerPayBillOnline',
                callbackUrl: 'https://example.com/callback',
                transactionDesc: 'BrifNet payment',
            ),
            businessShortCode: '174379',
            passkey: 'test-passkey',
            stkPushUrl: 'https://example.com/stkpush',
            stkQueryUrl: 'https://example.com/stkpushquery',
        );
    }

    private function createSuccessfulAccessTokenProvider(): FakeAccessTokenProvider
    {
        $provider = new FakeAccessTokenProvider();

        $provider->result = new AccessTokenResult(
            successful: true,
            accessToken: 'test-access-token',
            expiresIn: 3599,
        );

        return $provider;
    }


    public function testSuccessfulStkPushIsAccepted(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'MerchantRequestID' => '29115-123456789',
                'CheckoutRequestID' => 'ws_CO_123456789',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ], JSON_THROW_ON_ERROR),
        );

        $clock = new FakeClock('20260902130000');

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: $clock,
        );

        $result = $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertInstanceOf(
            StkPushResult::class,
            $result
        );

        $this->assertTrue($result->accepted);

        $this->assertSame(
            '29115-123456789',
            $result->merchantRequestId
        );

        $this->assertSame(
            'ws_CO_123456789',
            $result->checkoutRequestId
        );

        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function testClientBuildsCorrectHttpRequest(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'MerchantRequestID' => '29115-123456789',
                'CheckoutRequestID' => 'ws_CO_123456789',
                'ResponseCode' => '0',
            ], JSON_THROW_ON_ERROR),
        );

        $clock = new FakeClock('20260902130000');

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: $clock,
        );

        $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertSame(
            1,
            $httpClient->callCount
        );

        $this->assertSame(
            'https://example.com/stkpush',
            $httpClient->url
        );

        
        $this->assertSame(
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer test-access-token',
            ],
            $httpClient->headers
        );
        

        $payload = json_decode(
            $httpClient->body ?? '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            '174379',
            $payload['BusinessShortCode']
        );

        $this->assertSame(
            base64_encode(
                '174379test-passkey20260902130000'
            ),
            $payload['Password']
        );

        $this->assertSame(
            '20260902130000',
            $payload['Timestamp']
        );

        $this->assertSame(
            '500',
            $payload['Amount']
        );

       $this->assertSame(
            '254712345678',
            $payload['PartyA']
        );

        $this->assertSame(
            '174379',
            $payload['PartyB']
        );

        $this->assertSame(
            '254712345678',
            $payload['PhoneNumber']
        );

        $this->assertSame(
            'https://example.com/callback',
            $payload['CallBackURL']
        );

        $this->assertSame(
            'BRIF-001',
            $payload['AccountReference']
        );

        $this->assertSame(
            'BrifNet payment',
            $payload['TransactionDesc']
        );
    }

    public function testProviderRejectionIsReturnedAsRejectedResult(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'ResponseCode' => '1',
                'ResponseDescription' => 'Insufficient funds',
            ], JSON_THROW_ON_ERROR),
        );

        $clock = new FakeClock('20260902130000');

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: $clock,
        );

        $result = $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertFalse($result->accepted);

        $this->assertSame(
            '1',
            $result->errorCode
        );

        $this->assertSame(
            'Insufficient funds',
            $result->errorMessage
        );

        $this->assertNull($result->merchantRequestId);
        $this->assertNull($result->checkoutRequestId);
    }

    public function testHttpErrorIsReturnedAsRejectedResult(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 500,
            body: json_encode([
                'errorCode' => '500',
                'errorMessage' => 'Internal server error',
            ], JSON_THROW_ON_ERROR),
        );

        $clock = new FakeClock('20260902130000');

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: $clock,
        );

        $result = $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertFalse($result->accepted);

        $this->assertSame(
            '500',
            $result->errorCode
        );

        $this->assertSame(
            'Internal server error',
            $result->errorMessage
        );

        $this->assertNull($result->merchantRequestId);
        $this->assertNull($result->checkoutRequestId);
    }

    public function testAccessTokenIsSentAsBearerToken(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'MerchantRequestID' => '29115-123456789',
                'CheckoutRequestID' => 'ws_CO_123456789',
                'ResponseCode' => '0',
            ], JSON_THROW_ON_ERROR),
        );


        $accessTokenProvider = new FakeAccessTokenProvider();

        $accessTokenProvider->result = new AccessTokenResult(
            successful: true,
            accessToken: 'test-access-token',
            expiresIn: 3599,
        );       

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
            accessTokenProvider: $accessTokenProvider,
        );

        $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertSame(
            'Bearer test-access-token',
            $httpClient->headers['Authorization']
        );
    }

    public function testStkPushIsNotSentWhenAuthenticationFails(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'ResponseCode' => '0',
            ], JSON_THROW_ON_ERROR),
        );

        $accessTokenProvider = new FakeAccessTokenProvider();

        $accessTokenProvider->result = new AccessTokenResult(
            successful: false,
            accessToken: null,
            error: 'Invalid credentials.',
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
            accessTokenProvider: $accessTokenProvider,
        );


        $result = $client->initiateStkPush(
            $this->createPayment()
        );

        $this->assertFalse($result->accepted);

        $this->assertSame(
            'Invalid credentials.',
            $result->errorMessage
        );

        $this->assertSame(
            0,
            $httpClient->callCount
        );

        $this->assertSame(
            1,
            $accessTokenProvider->callCount
        );
    }

    public function testSuccessfulStkQueryReturnsResult(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'ResponseCode' => '0',
                'ResponseDescription' => 'The service request is processed successfully.',
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
        );

        $result = $client->queryStkPush(
            'ws_CO_123456789'
        );

        $this->assertInstanceOf(
            StkQueryResult::class,
            $result
        );

        $this->assertTrue($result->successful);

        $this->assertSame(
            '0',
            $result->resultCode
        );

        $this->assertSame(
            'The service request is processed successfully.',
            $result->resultDescription
        );
    }

    public function testStkQueryBuildsCorrectHttpRequest(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'ResponseCode' => '0',
                'ResponseDescription' => 'The service request is processed successfully.',
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
        );

        $client->queryStkPush(
            'ws_CO_123456789'
        );

        $this->assertSame(
            'POST',
            $httpClient->lastMethod
        );

        $this->assertSame(
            'https://example.com/stkpushquery',
            $httpClient->url
        );

        $this->assertSame(
            'application/json',
            $httpClient->headers['Content-Type']
        );

        $this->assertSame(
            'Bearer test-access-token',
            $httpClient->headers['Authorization']
        );

        $body = json_decode(
            $httpClient->body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            '174379',
            $body['BusinessShortCode']
        );

        $this->assertSame(
            base64_encode('174379test-passkey20260902130000'),
            $body['Password']
        );

        $this->assertSame(
            '20260902130000',
            $body['Timestamp']
        );

        $this->assertSame(
            'ws_CO_123456789',
            $body['CheckoutRequestID']
        );
    }

    public function testStkQueryIsNotSentWhenAuthenticationFails(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: '{}',
        );

        $accessTokenProvider = new FakeAccessTokenProvider();

        $accessTokenProvider->result = new AccessTokenResult(
            successful: false,
            accessToken: null,
            expiresIn: null,
            error: 'Invalid credentials.',
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
            accessTokenProvider: $accessTokenProvider,
        );

        $result = $client->queryStkPush(
            'ws_CO_123456789'
        );

        $this->assertFalse(
            $result->successful
        );

        $this->assertSame(
            'Invalid credentials.',
            $result->resultDescription
        );

        $this->assertSame(
            0,
            $httpClient->callCount
        );
    }

    public function testStkQueryHttpErrorReturnsRejectedResult(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 500,
            body: json_encode([
                'errorCode' => '500.001.1001',
                'errorMessage' => 'Internal server error.',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
        );

        $result = $client->queryStkPush(
            'ws_CO_123456789'
        );

        $this->assertFalse(
            $result->successful
        );

        $this->assertSame(
            'Internal server error.',
            $result->resultDescription
        );

        $this->assertSame(
            'ws_CO_123456789',
            $result->checkoutRequestId
        );
    }

    public function testSuccessfulStkQueryMapsAllResponseFields(): void
    {
        $httpClient = new FakeHttpClient();

        $httpClient->response = new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'ResponseCode' => '0',
                'ResponseDescription' =>
                    'The service request has been accepted successfully',
                'MerchantRequestID' => '22205-34066-1',
                'CheckoutRequestID' => 'ws_CO_13012021093521236557',
                'ResultCode' => '0',
                'ResultDesc' =>
                    'The service request is processed successfully.',
            ], JSON_THROW_ON_ERROR),
        );

        $client = $this->createClient(
            httpClient: $httpClient,
            clock: new FakeClock('20260902130000'),
        );

        $result = $client->queryStkPush(
            'ws_CO_13012021093521236557'
        );

        $this->assertTrue($result->successful);

        $this->assertSame(
            '0',
            $result->responseCode
        );

        $this->assertSame(
            'The service request has been accepted successfully',
            $result->responseDescription
        );

        $this->assertSame(
            '22205-34066-1',
            $result->merchantRequestId
        );

        $this->assertSame(
            'ws_CO_13012021093521236557',
            $result->checkoutRequestId
        );

        $this->assertSame(
            '0',
            $result->resultCode
        );

        $this->assertSame(
            'The service request is processed successfully.',
            $result->resultDescription
        );
    }

}
