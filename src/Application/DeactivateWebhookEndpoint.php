<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\WebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpointRepository;
use InvalidArgumentException;

final class DeactivateWebhookEndpoint
{
    public function __construct(
        private readonly WebhookEndpointRepository $repository,
    ) {}

    public function execute(string $url): WebhookEndpoint
    {
        $endpoint = $this->repository->findByUrl($url);

        if ($endpoint === null) {
            throw new InvalidArgumentException(
                'Webhook endpoint not found.'
            );
        }

        $updated = $endpoint->deactivate();

        $this->repository->update($updated);

        return $updated;
    }
}