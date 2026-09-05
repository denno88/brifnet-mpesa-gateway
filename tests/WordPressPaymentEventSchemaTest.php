<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\WordPressPaymentEventSchema;
use PHPUnit\Framework\TestCase;

final class WordPressPaymentEventSchemaTest extends TestCase
{
    public function testEventSchemaCreatesPaymentEventsTable(): void
    {
        $database = new FakeDatabase();
        $executor = new FakeSchemaExecutor();

        $schema = new WordPressPaymentEventSchema(
            database: $database,
            executor: $executor,
            tableName: 'wp_brifnet_mpesa_payment_events',
        );

        $schema->install();

        $this->assertCount(1, $executor->executedSql);
        $sql = $executor->executedSql[0];

        $this->assertStringContainsString(
            'CREATE TABLE wp_brifnet_mpesa_payment_events',
            $sql
        );

        $this->assertStringContainsString(
            'event_id VARCHAR(100) NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'event_name VARCHAR(100) NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'payment_reference VARCHAR(100) NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'payload LONGTEXT NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'occurred_at DATETIME NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'UNIQUE KEY event_id (event_id)',
            $sql
        );
    }
}