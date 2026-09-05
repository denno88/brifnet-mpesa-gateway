<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\HttpClient;
use BrifnetMpesa\Api\HttpResponse;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    public function testFakeHttpClientCanSendPostRequest(): void
    {
        $client = new FakeHttpClient();

        $client->response = new HttpResponse(
            statusCode: 200,
            body: '{"success":true}',
        );

        $result = $client->post(
            url: 'https://example.com/api',
            headers: [
                'Content-Type' => 'application/json',
            ],
            body: '{"amount":500}',
        );

        $this->assertInstanceOf(
            HttpClient::class,
            $client
        );

        $this->assertSame(
            1,
            $client->callCount
        );

        $this->assertSame(
            'https://example.com/api',
            $client->url
        );

        $this->assertSame(
            [
                'Content-Type' => 'application/json',
            ],
            $client->headers
        );

        $this->assertSame(
            '{"amount":500}',
            $client->body
        );

        $this->assertSame(
            200,
            $result->statusCode
        );

        $this->assertSame(
            '{"success":true}',
            $result->body
        );
    }
}
