<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\WebhookHttpClient;

final class FakeWebhookHttpClient implements WebhookHttpClient
{
    public ?string $url = null;

    public ?string $payload = null;

    public bool $shouldFail = false;

    public ?string $failUrl = null;

    public array $headers = [];

    public function send(
        string $url,
        string $payload,
        array $headers = [],
    ): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Webhook delivery failed.');
        }

        $this->url = $url;
        $this->payload = $payload;
        $this->headers = $headers;
    }
}