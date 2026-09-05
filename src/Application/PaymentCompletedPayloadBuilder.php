<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\PaymentCompleted;

final class PaymentCompletedPayloadBuilder
{
    public function build(PaymentCompleted $event): array
    {
        return [
            'event_id' => $event->eventId,
            'event' => $event->name(),
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            'data' => [
                'reference' => $event->payment->reference->value,
                'phone' => $event->payment->phone->value,
                'amount' => $event->payment->amount,
                'channel' => $event->payment->channel->value,
            ],
        ];
    }
}