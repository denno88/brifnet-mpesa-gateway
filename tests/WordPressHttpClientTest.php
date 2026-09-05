<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WordPressHttpClientTest extends TestCase
{
    public function testPostRequestIsConvertedToHttpResponse(): void
    {
        $transport = new FakeWordPressHttpTransport();

        $transport->statusCode = 200;
        $transport->body = '{"ResponseCode":"0"}';

        $client = new WordPressHttpClient($transport);

        $response = $client->post(
            url: 'https://example.com/stkpush',
            headers: [
                'Authorization' => 'Bearer test-token',
            ],
            body: '{"Amount":500}',
        );

        $this->assertSame('POST', $transport->lastMethod);
        $this->assertSame(
            'https://example.com/stkpush',
            $transport->url
        );

        $this->assertSame(
            'Bearer test-token',
            $transport->args['headers']['Authorization']
        );

        $this->assertSame(
            '{"Amount":500}',
            $transport->args['body']
        );

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(
            '{"ResponseCode":"0"}',
            $response->body
        );
    }

    public function testGetRequestIsConvertedToHttpResponse(): void
    {
        $transport = new FakeWordPressHttpTransport();

        $transport->statusCode = 200;
        $transport->body = '{"access_token":"token"}';

        $client = new WordPressHttpClient($transport);

        $response = $client->get(
            url: 'https://example.com/oauth',
            headers: [
                'Authorization' => 'Basic credentials',
            ],
        );

        $this->assertSame('GET', $transport->lastMethod);
        $this->assertSame(
            'https://example.com/oauth',
            $transport->url
        );

        $this->assertSame(
            'Basic credentials',
            $transport->args['headers']['Authorization']
        );

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(
            '{"access_token":"token"}',
            $response->body
        );
    }

    public function testWordPressHttpErrorBecomesRuntimeException(): void
    {
        $transport = new FakeWordPressHttpTransport();

        $transport->error = true;
        $transport->errorMessageValue = 'Connection failed';

        $client = new WordPressHttpClient($transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');

        $client->post(
            url: 'https://example.com/stkpush',
            headers: [],
            body: '{}',
        );
    }
}
