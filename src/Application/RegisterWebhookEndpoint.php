<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\WebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpointRepository;
use InvalidArgumentException;

final class RegisterWebhookEndpoint
{
    public function __construct(
        private readonly WebhookEndpointRepository $repository,
    ) {}

    public function execute(
        string $url,
        array $events,
    ): WebhookEndpoint {
        if ($this->repository->findByUrl($url) !== null) {
            throw new InvalidArgumentException(
                'Webhook endpoint already exists.'
            );
        }

        $endpoint = new WebhookEndpoint(
            url: $url,
            events: $events,
        );

        $this->repository->save($endpoint);

        return $endpoint;
    }
}