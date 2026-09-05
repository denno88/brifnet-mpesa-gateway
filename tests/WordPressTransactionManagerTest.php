<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressTransactionManager;
use PHPUnit\Framework\TestCase;

final class WordPressTransactionManagerTest extends TestCase
{
    public function testItBeginsATransaction(): void
    {
        $database = new FakeDatabase();

        $manager = new WordPressTransactionManager(
            database: $database,
        );

        $manager->begin();

        self::assertSame(
            ['begin'],
            $database->transactionOperations
        );
    }

    public function testItCommitsATransaction(): void
    {
        $database = new FakeDatabase();

        $manager = new WordPressTransactionManager(
            database: $database,
        );

        $manager->commit();

        self::assertSame(
            ['commit'],
            $database->transactionOperations
        );
    }

    public function testItRollsBackATransaction(): void
    {
        $database = new FakeDatabase();

        $manager = new WordPressTransactionManager(
            database: $database,
        );

        $manager->rollback();

        self::assertSame(
            ['rollback'],
            $database->transactionOperations
        );
    }
}