<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface WordPressHttpTransport
{
    public function get(
        string $url,
        array $args = [],
    ): mixed;

    public function post(
        string $url,
        array $args = [],
    ): mixed;

    public function isError(mixed $response): bool;

    public function errorMessage(mixed $response): string;

    public function responseCode(mixed $response): int;

    public function responseBody(mixed $response): string;
}
