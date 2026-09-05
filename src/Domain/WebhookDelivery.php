<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

final readonly class WebhookDelivery
{
    public function __construct(
        public string $eventId,
        public string $url,
        public string $payload,
        public string $status = 'pending',
        public int $attempts = 0,
    ) {
    }

    public function markDelivered(): self
    {
        return new self(
            eventId: $this->eventId,
            url: $this->url,
            payload: $this->payload,
            status: 'delivered',
            attempts: $this->attempts,
        );
    }

    public function markFailed(): self
    {
        return new self(
            eventId: $this->eventId,
            url: $this->url,
            payload: $this->payload,
            status: 'pending',
            attempts: $this->attempts + 1,
        );
    }

    public function isRetryable(int $maxAttempts): bool
    {
        return $this->attempts < $maxAttempts;
    }

    public function markPermanentlyFailed(): self
    {
        return new self(
            eventId: $this->eventId,
            url: $this->url,
            payload: $this->payload,
            status: 'failed',
            attempts: $this->attempts,
        );
    }
}