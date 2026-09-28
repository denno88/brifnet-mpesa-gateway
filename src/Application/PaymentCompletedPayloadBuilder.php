<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentCompleted;

final class PaymentCompletedPayloadBuilder
{
    public function build(PaymentCompleted $event): array
    {
        $payment = $event->payment;

        $data = [
            'phone' => $payment->phone->value,
            'amount' => $payment->amount,
            'channel' => $payment->channel->value,
        ];

        if ($payment->channel === PaymentChannel::STK) {
            $data = [
                'reference' => $payment->reference->value,
                'phone' => $payment->phone->value,
                'amount' => $payment->amount,
                'channel' => $payment->channel->value,
                'provider_reference' => $payment->checkoutRequestId,
                'provider_transaction_id' => $payment->transactionId,
            ];
        }

        if ($payment->channel === PaymentChannel::C2B) {
            $data = [
                'account_number' => $payment->accountNumber,
                'phone' => $payment->phone->value,
                'amount' => $payment->amount,
                'channel' => $payment->channel->value,
                'provider_transaction_id' => $payment->transactionId,
            ];
        }

        return [
            'event_id' => $event->eventId,
            'event' => $event->name(),
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            'data' => $data,
        ];
    }
}