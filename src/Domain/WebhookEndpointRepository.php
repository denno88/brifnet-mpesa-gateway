<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

interface WebhookEndpointRepository
{
    public function save(WebhookEndpoint $endpoint): void;

    public function findByUrl(string $url): ?WebhookEndpoint;

    public function update(WebhookEndpoint $endpoint): void;

    public function findActiveForEvent(string $event): array;
}