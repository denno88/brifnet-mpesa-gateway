<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\SchemaExecutor;

final class FakeSchemaExecutor implements SchemaExecutor
{
    public array $executedSql = [];

    public function execute(string $sql): void
    {
        $this->executedSql[] = $sql;
    }
}