<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\NativeWordPressDatabase;
use PHPUnit\Framework\TestCase;

final class NativeWordPressDatabaseTest extends TestCase
{
    public function testItBeginsATransaction(): void
    {
        $database = new class {
            public array $queries = [];

            public function query(string $query): void
            {
                $this->queries[] = $query;
            }
        };

        $adapter = new NativeWordPressDatabase(
            database: $database
        );

        $adapter->beginTransaction();

        self::assertSame(
            ['START TRANSACTION'],
            $database->queries
        );
    }

    public function testItCommitsATransaction(): void
    {
        $database = new class {
            public array $queries = [];

            public function query(string $query): void
            {
                $this->queries[] = $query;
            }
        };

        $adapter = new NativeWordPressDatabase(
            database: $database
        );

        $adapter->commit();

        self::assertSame(
            ['COMMIT'],
            $database->queries
        );
    }

    public function testItRollsBackATransaction(): void
    {
        $database = new class {
            public array $queries = [];

            public function query(string $query): void
            {
                $this->queries[] = $query;
            }
        };

        $adapter = new NativeWordPressDatabase(
            database: $database
        );

        $adapter->rollback();

        self::assertSame(
            ['ROLLBACK'],
            $database->queries
        );
    }
}