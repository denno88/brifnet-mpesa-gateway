<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Api\DarajaConfig;
use Dotenv\Dotenv;
use RuntimeException;

final class DarajaConfigLoader
{
    public function load(): DarajaConfig
    {
        $dotenvPath = 'C:/xampp/brifnet-secrets';

        $dotenv = Dotenv::createImmutable($dotenvPath);
        $dotenv->load();

        return new DarajaConfig(
            consumerKey: $this->env('DARAJA_CONSUMER_KEY'),
            consumerSecret: $this->env('DARAJA_CONSUMER_SECRET'),
            businessShortCode: $this->env('DARAJA_BUSINESS_SHORTCODE'),
            passkey: $this->env('DARAJA_PASSKEY'),
            authUrl: $this->env('DARAJA_AUTH_URL'),
            stkPushUrl: $this->env('DARAJA_STK_PUSH_URL'),
            stkQueryUrl: $this->env('DARAJA_STK_QUERY_URL'),
            callbackUrl: $this->env('DARAJA_CALLBACK_URL'),
        );
    }

    private function env(string $key): string
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null || $value === '') {
            throw new RuntimeException(
                "Missing required environment variable: {$key}"
            );
        }

        return $value;
    }
}