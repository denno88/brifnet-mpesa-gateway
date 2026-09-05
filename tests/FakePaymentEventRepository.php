<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PaymentEventRepository;

final class FakePaymentEventRepository implements PaymentEventRepository
{
    public array $events = [];

    public int $saveCount = 0;

    public function save(PaymentCompleted $event): void
    {
        $this->saveCount++;

        $this->events[$event->eventId] = $event;
    }

    public function findByEventId(string $eventId): ?PaymentCompleted
    {
        return $this->events[$eventId] ?? null;
    }
}