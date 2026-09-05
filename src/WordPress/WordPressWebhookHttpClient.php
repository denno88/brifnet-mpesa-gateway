<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Api\HttpClient;
use BrifnetMpesa\Domain\WebhookHttpClient;

final class WordPressWebhookHttpClient implements WebhookHttpClient
{
    public function __construct(
        private readonly HttpClient $http,
    ) {
    }

    public function send(
        string $url,
        string $payload,
        array $headers = [],
    ): void {
        $response = $this->http->post(
            $url,
            array_merge(
                [
                    'Content-Type' => 'application/json',
                ],
                $headers,
            ),
            $payload,
        );

        if (
            $response->statusCode < 200
            || $response->statusCode >= 300
        ) {
            throw new \RuntimeException(
                'Webhook request failed with HTTP status '
                . $response->statusCode
            );
        }
    }
}