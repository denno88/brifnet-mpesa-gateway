<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentRepository;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Domain\DuplicatePaymentException;
use BrifnetMpesa\WordPress\WordPressDatabase;
use BrifnetMpesa\Api\DateTimeProvider;

final class WordPressPaymentRepository implements PaymentRepository
{
    public function __construct(
        private readonly WordPressDatabase $database,
        private readonly string $tableName,
        private readonly DateTimeProvider $dateTimeProvider,
    ) {
    }

    public function save(Payment $payment): void
    {
        $now = $this->dateTimeProvider->now()->format('Y-m-d H:i:s');
        $result = $this->database->insert(
            $this->tableName,
            [
                'reference' => $payment->reference->value,
                'phone' => $payment->phone->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'payment_channel' => $payment->channel->value,
                'merchant_request_id' => $payment->merchantRequestId,
                'checkout_request_id' => $payment->checkoutRequestId,
                'trans_id' => $payment->transactionId,
                'bill_ref_number' => $payment->accountNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            $error = $this->database->getLastError();

            if (
                str_contains(
                    strtolower($error),
                    'duplicate'
                )
            ) {
                throw new DuplicatePaymentException(
                    'Payment already exists.'
                );
            }

            throw new \RuntimeException(
                'Failed to save payment.'
            );
        }
    }

    public function update(Payment $payment): void
    {
        $result = $this->database->update(
            $this->tableName,
            [
                'status' => $payment->status->value,
                'merchant_request_id' => $payment->merchantRequestId,
                'checkout_request_id' => $payment->checkoutRequestId,
                'trans_id' => $payment->transactionId,
                'bill_ref_number' => $payment->accountNumber,
            ],
            [
                'reference' => $payment->reference->value,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            [
                '%s',
            ]
        );

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to update payment.'
            );
        }
    }

    public function findByCheckoutRequestId(
        string $checkoutRequestId
    ): ?Payment {
        $query = $this->database->prepare(
            "SELECT
                reference,
                phone,
                amount,
                status,
                payment_channel,
                merchant_request_id,
                checkout_request_id,
                trans_id,
                bill_ref_number
            FROM {$this->tableName}
            WHERE checkout_request_id = %s
            LIMIT 1",
            $checkoutRequestId
        );

        $row = $this->database->get_row($query);

        if (!is_object($row)) {
            return null;
        }

        return new Payment(
            reference: new PaymentReference(
                (string) $row->reference
            ),
            phone: new PhoneNumber(
                (string) $row->phone
            ),
            amount: (int) $row->amount,
            channel: PaymentChannel::from(
                (string) $row->payment_channel
            ),
            status: PaymentStatus::from(
                (string) $row->status
            ),
            merchantRequestId: isset($row->merchant_request_id)
                ? (string) $row->merchant_request_id
                : null,
            checkoutRequestId: isset($row->checkout_request_id)
                ? (string) $row->checkout_request_id
                : null,
            transactionId: isset($row->trans_id)
                ? (string) $row->trans_id
                : null,
            accountNumber: isset($row->bill_ref_number)
                ? (string) $row->bill_ref_number
                : null,
        );
    }

    public function findByTransactionId(
        string $transactionId
    ): ?Payment {
        $query = $this->database->prepare(
            "SELECT
                reference,
                phone,
                amount,
                status,
                payment_channel,
                merchant_request_id,
                checkout_request_id,
                trans_id,
                bill_ref_number
            FROM {$this->tableName}
            WHERE trans_id = %s
            LIMIT 1",
            $transactionId
        );

        $row = $this->database->get_row($query);

        if (!is_object($row)) {
            return null;
        }

        return new Payment(
            reference: new PaymentReference(
                (string) $row->reference
            ),
            phone: new PhoneNumber(
                (string) $row->phone
            ),
            amount: (int) $row->amount,
            channel: PaymentChannel::from(
                (string) $row->payment_channel
            ),
            status: PaymentStatus::from(
                (string) $row->status
            ),
            merchantRequestId: isset($row->merchant_request_id)
                ? (string) $row->merchant_request_id
                : null,
            checkoutRequestId: isset($row->checkout_request_id)
                ? (string) $row->checkout_request_id
                : null,
            transactionId: isset($row->trans_id)
                ? (string) $row->trans_id
                : null,
            accountNumber: isset($row->bill_ref_number)
                ? (string) $row->bill_ref_number
                : null,
        );
    }
}
