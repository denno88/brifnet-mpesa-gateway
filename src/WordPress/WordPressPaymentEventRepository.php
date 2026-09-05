<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PaymentEventRepository;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use RuntimeException;

final class WordPressPaymentEventRepository implements PaymentEventRepository
{
    public function __construct(
        private readonly WordPressDatabase $database,
        private readonly string $tableName,
    ) {
    }

    public function save(PaymentCompleted $event): void
    {
        $payment = $event->payment;

        $payload = json_encode(
            [
                'reference' => $payment->reference->value,
                'phone' => $payment->phone->value,
                'amount' => $payment->amount,
                'channel' => $payment->channel->value,
                'status' => $payment->status->value,
                'merchant_request_id' => $payment->merchantRequestId,
                'checkout_request_id' => $payment->checkoutRequestId,
                'transaction_id' => $payment->transactionId,
            ],
            JSON_THROW_ON_ERROR
        );

        $now = new \DateTimeImmutable();

        $result = $this->database->insert(
            $this->tableName,
            [
                'event_id' => $event->eventId,
                'event_name' => $event->name(),
                'payment_reference' => $payment->reference->value,
                'payload' => $payload,
                'occurred_at' => $event->occurredAt->format(
                    'Y-m-d H:i:s'
                ),
                'created_at' => $now->format(
                    'Y-m-d H:i:s'
                ),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ],
        );

        if ($result === false) {
            $error = $this->database->getLastError();

            if (str_contains(strtolower($error), 'duplicate')) {
                throw new RuntimeException(
                    'Payment event already exists.'
                );
            }

            throw new RuntimeException(
                'Failed to save payment event.'
            );
        }
    }

    public function findByEventId(string $eventId): ?PaymentCompleted
    {
        $query = $this->database->prepare(
            "SELECT
                event_id,
                event_name,
                payment_reference,
                payload,
                occurred_at
            FROM {$this->tableName}
            WHERE event_id = %s
            LIMIT 1",
            $eventId
        );

        $row = $this->database->get_row($query);

        if (!is_object($row)) {
            return null;
        }

        $payload = json_decode(
            (string) $row->payload,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($payload)) {
            throw new RuntimeException(
                'Invalid payment event payload.'
            );
        }

        $payment = new Payment(
            reference: new PaymentReference(
                (string) $payload['reference']
            ),
            phone: new PhoneNumber(
                (string) $payload['phone']
            ),
            amount: (int) $payload['amount'],
            channel: PaymentChannel::from(
                (string) $payload['channel']
            ),
            status: PaymentStatus::from(
                (string) $payload['status']
            ),
            merchantRequestId: isset(
                $payload['merchant_request_id']
            )
                ? (string) $payload['merchant_request_id']
                : null,
            checkoutRequestId: isset(
                $payload['checkout_request_id']
            )
                ? (string) $payload['checkout_request_id']
                : null,
            transactionId: isset(
                $payload['transaction_id']
            )
                ? (string) $payload['transaction_id']
                : null,
        );

        return new PaymentCompleted(
            eventId: (string) $row->event_id,
            payment: $payment,
            occurredAt: new \DateTimeImmutable(
                (string) $row->occurred_at
            ),
        );
    }
}