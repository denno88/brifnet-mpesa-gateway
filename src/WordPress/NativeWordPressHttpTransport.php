<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class NativeWordPressHttpTransport implements WordPressHttpTransport
{
    public function get(
        string $url,
        array $args = [],
    ): mixed {
        return wp_remote_get($url, $args);
    }

    public function post(
        string $url,
        array $args = [],
    ): mixed {
        return wp_remote_post($url, $args);
    }

    public function isError(mixed $response): bool
    {
        return is_wp_error($response);
    }

    public function errorMessage(mixed $response): string
    {
        return $response->get_error_message();
    }

    public function responseCode(mixed $response): int
    {
        return (int) wp_remote_retrieve_response_code($response);
    }

    public function responseBody(mixed $response): string
    {
        return (string) wp_remote_retrieve_body($response);
    }
}
