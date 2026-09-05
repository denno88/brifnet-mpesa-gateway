<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\WordPressWebhookDeliverySchema;
use PHPUnit\Framework\TestCase;

final class WordPressWebhookDeliverySchemaTest extends TestCase
{
    public function test_schema_creates_webhook_deliveries_table(): void
    {
        $database = new FakeDatabase();
        $executor = new FakeSchemaExecutor();

        $schema = new WordPressWebhookDeliverySchema(
            database: $database,
            executor: $executor,
            tableName: 'wp_webhook_deliveries',
        );

        $schema->install();

        self::assertStringContainsString(
            'CREATE TABLE wp_webhook_deliveries',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'event_id VARCHAR(100) NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'url VARCHAR(500) NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'payload LONGTEXT NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'status VARCHAR(20) NOT NULL',
            $executor->executedSql[0]
        );

        self::assertStringContainsString(
            'attempts INT UNSIGNED NOT NULL',
            $executor->executedSql[0]
        );
    }
}