<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\WordPressWebhookEndpointSchema;
use PHPUnit\Framework\TestCase;

final class WordPressWebhookEndpointSchemaTest extends TestCase
{
    public function test_schema_creates_webhook_endpoints_table(): void
    {
        $database = new FakeDatabase();
        $executor = new FakeSchemaExecutor();

        $schema = new WordPressWebhookEndpointSchema(
            database: $database,
            executor: $executor,
            tableName: 'wp_webhook_endpoints',
        );

        $schema->install();

        self::assertStringContainsString(
            'CREATE TABLE wp_webhook_endpoints',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'url VARCHAR(500) NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'active TINYINT(1) NOT NULL DEFAULT 1',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'events LONGTEXT NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'created_at DATETIME NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'updated_at DATETIME NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'UNIQUE KEY url (url)',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'KEY active (active)',
            $executor->executedSql[0]
        );
    }
}