<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use InvalidArgumentException;
use JsonException;

final class C2BPaymentParser
{
    public function parse(string $json): C2BPayment
    {
        try {
            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Invalid C2B callback JSON.',
                previous: $exception
            );
        }

        $transactionId = $data['TransID'] ?? null;
        $phone = $data['MSISDN'] ?? null;
        $amount = $data['TransAmount'] ?? null;
        $billReference = $data['BillRefNumber'] ?? null;
        $transactionTime = $data['TransTime'] ?? null;
        $businessShortCode = $data['BusinessShortCode'] ?? null;

        if (!is_string($transactionId) || $transactionId === '') {
            throw new InvalidArgumentException(
                'Missing transaction ID.'
            );
        }

        if (!is_string($phone) || $phone === '') {
            throw new InvalidArgumentException(
                'Missing phone number.'
            );
        }

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException(
                'Missing or invalid transaction amount.'
            );
        }

        if (
            !is_string($billReference) ||
            $billReference === ''
        ) {
            throw new InvalidArgumentException(
                'Missing bill reference number.'
            );
        }

        if (
            !is_string($transactionTime) ||
            $transactionTime === ''
        ) {
            throw new InvalidArgumentException(
                'Missing transaction time.'
            );
        }

        if (
            !is_string($businessShortCode) ||
            $businessShortCode === ''
        ) {
            throw new InvalidArgumentException(
                'Missing business short code.'
            );
        }

        return new C2BPayment(
            transactionId: $transactionId,
            phone: $this->normalizePhoneNumber($phone),
            amount: (int) $amount,
            billReferenceNumber: $billReference,
            transactionTime: $transactionTime,
            businessShortCode: $businessShortCode,
        );
    }

    private function normalizePhoneNumber(string $phone): string
    {
        if (preg_match('/^2547\d{8}$/', $phone) === 1) {
            return '0' . substr($phone, 3);
        }

        return $phone;
    }
}