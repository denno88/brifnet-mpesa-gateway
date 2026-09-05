<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

final class FakeWordPressHttp
{
    public ?string $url = null;

    public ?string $method = null;

    public ?string $body = null;

    public bool $shouldFail = false;

    public function post(
        string $url,
        array $args,
    ): void {
        if ($this->shouldFail) {
            throw new \RuntimeException(
                'HTTP request failed.'
            );
        }

        $this->url = $url;
        $this->method = $args['method'] ?? null;
        $this->body = $args['body'] ?? null;
    }
}