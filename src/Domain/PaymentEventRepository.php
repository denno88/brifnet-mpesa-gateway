<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

interface PaymentEventRepository
{
    public function save(PaymentCompleted $event): void;

    public function findByEventId(string $eventId): ?PaymentCompleted;
}