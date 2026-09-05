<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;


interface WebhookDeliveryRepository
{
    public function save(WebhookDelivery $delivery): void;

    public function findByEventIdAndUrl(
        string $eventId,
        string $url
    ): ?WebhookDelivery;

    public function findPending(): array;

    public function update(WebhookDelivery $delivery): void;
}