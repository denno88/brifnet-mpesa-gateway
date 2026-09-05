<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

use InvalidArgumentException;

final class StkCallbackParser
{
    public function parse(string $json): StkCallbackResult
    {
        try {
            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(
                'Invalid callback JSON.',
                previous: $exception
            );
        }

        $callback = $data['Body']['stkCallback'] ?? null;

        if (!is_array($callback)) {
            throw new InvalidArgumentException(
                'Invalid STK callback payload.'
            );
        }

        $merchantRequestId = $callback['MerchantRequestID'] ?? null;
        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = $callback['ResultCode'] ?? null;
        $resultDescription = $callback['ResultDesc'] ?? null;

        if (
            !is_string($merchantRequestId) ||
            $merchantRequestId === ''
        ) {
            throw new InvalidArgumentException(
                'Missing MerchantRequestID.'
            );
        }

        if (
            !is_string($checkoutRequestId) ||
            $checkoutRequestId === ''
        ) {
            throw new InvalidArgumentException(
                'Missing CheckoutRequestID.'
            );
        }

        if (!is_int($resultCode) && !is_numeric($resultCode)) {
            throw new InvalidArgumentException(
                'Missing ResultCode.'
            );
        }

        if (
            !is_string($resultDescription) ||
            $resultDescription === ''
        ) {
            throw new InvalidArgumentException(
                'Missing ResultDesc.'
            );
        }

        $metadata = $this->extractMetadata($callback);

        return new StkCallbackResult(
            merchantRequestId: $merchantRequestId,
            checkoutRequestId: $checkoutRequestId,
            resultCode: (int) $resultCode,
            resultDescription: $resultDescription,
            amount: $metadata['amount'],
            receipt: $metadata['receipt'],
            phone: $metadata['phone'],
        );
    }

    private function extractMetadata(array $callback): array
    {
        $items = $callback['CallbackMetadata']['Item'] ?? [];

        if (!is_array($items)) {
            return [
                'amount' => null,
                'receipt' => null,
                'phone' => null,
            ];
        }

        $amount = null;
        $receipt = null;
        $phone = null;

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['Name'])) {
                continue;
            }

            $name = (string) $item['Name'];

            if ($name === 'Amount' && isset($item['Value'])) {
                $amount = (int) $item['Value'];
            }

            if (
                $name === 'MpesaReceiptNumber' &&
                isset($item['Value'])
            ) {
                $receipt = (string) $item['Value'];
            }

            if (
                $name === 'PhoneNumber' &&
                isset($item['Value'])
            ) {
                $phone = $this->normalizePhoneNumber(
                    (string) $item['Value']
                );
            }
        }

        return [
            'amount' => $amount,
            'receipt' => $receipt,
            'phone' => $phone,
        ];
    }

    private function normalizePhoneNumber(string $phone): string
    {
        if (preg_match('/^2547\d{8}$/', $phone) === 1) {
            return '0' . substr($phone, 3);
        }

        return $phone;
    }
}
