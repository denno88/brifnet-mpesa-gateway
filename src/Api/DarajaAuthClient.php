<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class DarajaAuthClient implements AccessTokenAuthenticator
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $authUrl,
    ) {
    }

    public function authenticate(): AccessTokenResult
    {
        $credentials = base64_encode(
            $this->consumerKey . ':' . $this->consumerSecret
        );

        $response = $this->httpClient->get(
            url: $this->authUrl . '?grant_type=client_credentials',
            headers: [
                'Authorization' => 'Basic ' . $credentials,
            ],
        );

        $data = json_decode(
            $response->body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            $response->statusCode < 200 ||
            $response->statusCode >= 300
        ) {
            return new AccessTokenResult(
                successful: false,
                accessToken: null,
                error: isset($data['error_description'])
                    ? (string) $data['error_description']
                    : 'Authentication request failed.',
            );
        }

        if (
            !isset($data['access_token']) ||
            !is_string($data['access_token']) ||
            $data['access_token'] === ''
        ) {
            return new AccessTokenResult(
                successful: false,
                accessToken: null,
                error: 'Authentication response did not contain an access token.',
            );
        }

       
        return new AccessTokenResult(
            successful: true,
            accessToken: $data['access_token'],
            expiresIn: isset($data['expires_in'])
                ? (int) $data['expires_in']
                : null,
        );

    }
}
