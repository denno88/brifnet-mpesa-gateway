<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookDelivery;
use BrifnetMpesa\Domain\WebhookDeliveryRepository;

final class FakeWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    public array $deliveries = [];

    public function save(WebhookDelivery $delivery): void
    {
        $key = $this->key(
            $delivery->eventId,
            $delivery->url
        );

        $this->deliveries[$key] = $delivery;
    }

    public function findByEventIdAndUrl(
        string $eventId,
        string $url
    ): ?WebhookDelivery {
        return $this->deliveries[
            $this->key($eventId, $url)
        ] ?? null;
    }

    public function findPending(): array
    {
        return array_values(
            array_filter(
                $this->deliveries,
                fn (WebhookDelivery $delivery): bool =>
                    $delivery->status === 'pending'
            )
        );
    }

    private function key(string $eventId, string $url): string
    {
        return $eventId . '|' . $url;
    }

    public function update(WebhookDelivery $delivery): void
    {
        $key = $this->key(
            $delivery->eventId,
            $delivery->url
        );

        $this->deliveries[$key] = $delivery;
    }
}