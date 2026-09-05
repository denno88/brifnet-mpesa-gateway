<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Api\HttpClient;
use BrifnetMpesa\Api\HttpResponse;
use RuntimeException;

final class WordPressHttpClient implements HttpClient
{
    public function __construct(
        private readonly WordPressHttpTransport $transport,
    ) {
    }

    public function get(
        string $url,
        array $headers = [],
    ): HttpResponse {
        $response = $this->transport->get(
            $url,
            [
                'headers' => $headers,
                'timeout' => 30,
            ]
        );

        if ($this->transport->isError($response)) {
            throw new RuntimeException(
                $this->transport->errorMessage($response)
            );
        }

        return new HttpResponse(
            statusCode: $this->transport->responseCode($response),
            body: $this->transport->responseBody($response),
        );
    }

    public function post(
        string $url,
        array $headers,
        string $body,
    ): HttpResponse {
        $response = $this->transport->post(
            $url,
            [
                'headers' => $headers,
                'body' => $body,
                'timeout' => 30,
            ]
        );

        if ($this->transport->isError($response)) {
            throw new RuntimeException(
                $this->transport->errorMessage($response)
            );
        }

        return new HttpResponse(
            statusCode: $this->transport->responseCode($response),
            body: $this->transport->responseBody($response),
        );
    }
}
