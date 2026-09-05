<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Api\AccessTokenProvider;

final class DarajaMpesaClient implements MpesaClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly AccessTokenProvider $accessTokenProvider,
        private readonly Clock $clock,
        private readonly StkPasswordGenerator $passwordGenerator,
        private readonly StkPushRequestBuilder $requestBuilder,
        private readonly string $businessShortCode,
        private readonly string $passkey,
        private readonly string $stkPushUrl,
        private readonly string $stkQueryUrl,
    ) {
    }

    public function initiateStkPush(Payment $payment): StkPushResult
    {
        $authResult = $this->accessTokenProvider->getAccessToken();

        if (!$authResult->successful || $authResult->accessToken === null) {
            return new StkPushResult(
                accepted: false,
                merchantRequestId: null,
                checkoutRequestId: null,
                errorMessage: $authResult->error
                    ?? 'M-Pesa authentication failed.',
            );
        }

        $timestamp = $this->clock->timestamp();

        $password = $this->passwordGenerator->generate(
            businessShortCode: $this->businessShortCode,
            passkey: $this->passkey,
            timestamp: $timestamp,
        );

        $request = $this->requestBuilder->build(
            payment: $payment,
            password: $password,
            timestamp: $timestamp,
        );

        $response = $this->httpClient->post(
            url: $this->stkPushUrl,
            headers: [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $authResult->accessToken,
            ],
            body: json_encode(
                $request->toArray(),
                JSON_THROW_ON_ERROR
            ),
        );

        $data = json_decode(
            $response->body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            $response->statusCode < 200 ||
            $response->statusCode >= 300
        ) {
            return new StkPushResult(
                accepted: false,
                merchantRequestId: null,
                checkoutRequestId: null,
                errorCode: isset($data['errorCode'])
                    ? (string) $data['errorCode']
                    : null,
                errorMessage: isset($data['errorMessage'])
                    ? (string) $data['errorMessage']
                    : null,
            );
        }

        return new StkPushResult(
            accepted: isset($data['ResponseCode'])
                && (string) $data['ResponseCode'] === '0',
            merchantRequestId: isset($data['MerchantRequestID'])
                ? (string) $data['MerchantRequestID']
                : null,
            checkoutRequestId: isset($data['CheckoutRequestID'])
                ? (string) $data['CheckoutRequestID']
                : null,
            errorCode: isset($data['ResponseCode'])
                && (string) $data['ResponseCode'] !== '0'
                ? (string) $data['ResponseCode']
                : null,
            errorMessage: isset($data['ResponseDescription'])
                && (string) $data['ResponseCode'] !== '0'
                ? (string) $data['ResponseDescription']
                : null,
        );
    }

    public function queryStkPush(
        string $checkoutRequestId
    ): StkQueryResult {
        $authResult = $this->accessTokenProvider->getAccessToken();

        if (
            !$authResult->successful ||
            $authResult->accessToken === null
        ) {
            return new StkQueryResult(
                successful: false,
                responseCode: null,
                responseDescription: null,
                merchantRequestId: null,
                checkoutRequestId: $checkoutRequestId,
                resultCode: null,
                resultDescription: $authResult->error
                    ?? 'M-Pesa authentication failed.',
            );
        }

        $timestamp = $this->clock->timestamp();

        $password = $this->passwordGenerator->generate(
            businessShortCode: $this->businessShortCode,
            passkey: $this->passkey,
            timestamp: $timestamp,
        );

        $payload = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = $this->httpClient->post(
            url: $this->stkQueryUrl,
            headers: [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $authResult->accessToken,
            ],
            body: json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            ),
        );

        $data = json_decode(
            $response->body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            $response->statusCode < 200 ||
            $response->statusCode >= 300
        ) {
            return new StkQueryResult(
                successful: false,
                responseCode: isset($data['ResponseCode'])
                    ? (string) $data['ResponseCode']
                    : null,
                responseDescription: isset($data['ResponseDescription'])
                    ? (string) $data['ResponseDescription']
                    : null,
                merchantRequestId: isset($data['MerchantRequestID'])
                    ? (string) $data['MerchantRequestID']
                    : null,
                checkoutRequestId: isset($data['CheckoutRequestID'])
                    ? (string) $data['CheckoutRequestID']
                    : $checkoutRequestId,
                resultCode: isset($data['ResultCode'])
                    ? (string) $data['ResultCode']
                    : null,
                resultDescription: isset($data['errorMessage'])
                    ? (string) $data['errorMessage']
                    : null,
            );
        }

        return new StkQueryResult(
            successful: isset($data['ResultCode'])
                && (string) $data['ResultCode'] === '0',

            responseCode: isset($data['ResponseCode'])
                ? (string) $data['ResponseCode']
                : null,

            responseDescription: isset($data['ResponseDescription'])
                ? (string) $data['ResponseDescription']
                : null,

            merchantRequestId: isset($data['MerchantRequestID'])
                ? (string) $data['MerchantRequestID']
                : null,

            checkoutRequestId: isset($data['CheckoutRequestID'])
                ? (string) $data['CheckoutRequestID']
                : $checkoutRequestId,

            resultCode: isset($data['ResultCode'])
                ? (string) $data['ResultCode']
                : null,

            resultDescription: isset($data['ResultDesc'])
                ? (string) $data['ResultDesc']
                : null,
        );
    }
}
