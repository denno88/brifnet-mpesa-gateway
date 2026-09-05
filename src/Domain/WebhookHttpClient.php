<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

interface WebhookHttpClient
{
    public function send(
        string $url,
        string $payload,
        array $headers = [],
    ): void;
}