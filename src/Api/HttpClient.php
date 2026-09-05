<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface HttpClient
{
    public function get(
        string $url,
        array $headers = [],
    ): HttpResponse;

    public function post(
        string $url,
        array $headers,
        string $body,
    ): HttpResponse;
}

