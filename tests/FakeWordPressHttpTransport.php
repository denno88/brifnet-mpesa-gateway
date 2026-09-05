<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressHttpTransport;

final class FakeWordPressHttpTransport implements WordPressHttpTransport
{
    public mixed $response = null;

    public ?string $url = null;

    public array $args = [];

    public string $lastMethod = '';

    public bool $error = false;

    public string $errorMessageValue = '';

    public int $statusCode = 200;

    public string $body = '';

    public function get(
        string $url,
        array $args = [],
    ): mixed {
        $this->lastMethod = 'GET';
        $this->url = $url;
        $this->args = $args;

        return $this->response;
    }

    public function post(
        string $url,
        array $args = [],
    ): mixed {
        $this->lastMethod = 'POST';
        $this->url = $url;
        $this->args = $args;

        return $this->response;
    }

    public function isError(mixed $response): bool
    {
        return $this->error;
    }

    public function errorMessage(mixed $response): string
    {
        return $this->errorMessageValue;
    }

    public function responseCode(mixed $response): int
    {
        return $this->statusCode;
    }

    public function responseBody(mixed $response): string
    {
        return $this->body;
    }
}
