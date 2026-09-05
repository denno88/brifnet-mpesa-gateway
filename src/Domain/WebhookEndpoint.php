<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

use InvalidArgumentException;

final readonly class WebhookEndpoint
{
    public function __construct(
        public string $url,
        public bool $active = true,
        public array $events = [],
    ) {
        if ($url === '') {
            throw new InvalidArgumentException(
                'Webhook URL cannot be empty.'
            );
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                'Webhook URL is invalid.'
            );
        }

        foreach ($events as $event) {
            if ($event === '') {
                throw new InvalidArgumentException(
                    'Webhook event name cannot be empty.'
                );
            }
        }
    }

    public function acceptsEvent(string $event): bool
    {
        return $this->active
            && in_array($event, $this->events, true);
    }

    public function deactivate(): self
    {
        return new self(
            url: $this->url,
            active: false,
            events: $this->events,
        );
    }

    public function activate(): self
    {
        return new self(
            url: $this->url,
            active: true,
            events: $this->events,
        );
    }
}