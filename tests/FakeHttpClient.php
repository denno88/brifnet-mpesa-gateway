<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\HttpClient;
use BrifnetMpesa\Api\HttpResponse;

final class FakeHttpClient implements HttpClient
{
    public ?string $url = null;

    public array $headers = [];

    public ?string $body = null;

    public int $callCount = 0;

    public string $lastMethod = '';

    public HttpResponse $response;

    public function __construct()
    {
        $this->response = new HttpResponse(
            statusCode: 200,
            body: '',
        );
    }

    public function get(
        string $url,
        array $headers = [],
    ): HttpResponse {
        $this->callCount++;
        $this->lastMethod = 'GET';

        $this->url = $url;
        $this->headers = $headers;
        $this->body = null;

        return $this->response;
    }

    public function post(
        string $url,
        array $headers,
        string $body,
    ): HttpResponse {
        $this->callCount++;
        $this->lastMethod = 'POST';

        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;

        return $this->response;
    }
}
