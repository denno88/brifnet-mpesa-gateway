<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpointRepository;

final class FakeWebhookEndpointRepository implements WebhookEndpointRepository
{
    public array $endpoints = [];

    public function save(WebhookEndpoint $endpoint): void
    {
        $this->endpoints[$endpoint->url] = $endpoint;
    }

    public function findByUrl(string $url): ?WebhookEndpoint
    {
        return $this->endpoints[$url] ?? null;
    }

    public function update(WebhookEndpoint $endpoint): void
    {
        $this->endpoints[$endpoint->url] = $endpoint;
    }

    public function findActiveForEvent(string $event): array
    {
        return array_values(
            array_filter(
                $this->endpoints,
                fn (WebhookEndpoint $endpoint): bool =>
                    $endpoint->acceptsEvent($event),
            )
        );
    }
}