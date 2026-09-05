<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\WordPressSchema;
use PHPUnit\Framework\TestCase;

final class WordPressSchemaTest extends TestCase
{
    public function testItCreatesTheTransactionTableSchema(): void
    {
        $database = new FakeDatabase();
        $executor = new FakeSchemaExecutor();

        $schema = new WordPressSchema(
            database: $database,
            executor: $executor,
            tableName: 'wp_brifnet_mpesa_transactions',
        );

        $schema->install();

        $this->assertCount(
            1,
            $executor->executedSql
        );

        $sql = $executor->executedSql[0];

        $this->assertStringContainsString(
            'CREATE TABLE wp_brifnet_mpesa_transactions',
            $sql
        );

        $this->assertStringContainsString(
            'reference VARCHAR(100) NOT NULL',
            $sql
        );

        $this->assertStringContainsString(
            'trans_id VARCHAR(100) NULL',
            $sql
        );

        $this->assertStringContainsString(
            'UNIQUE KEY trans_id (trans_id)',
            $sql
        );

        $this->assertStringContainsString(
            'integration_processed TINYINT(1) NOT NULL DEFAULT 0',
            $sql
        );
    }
}