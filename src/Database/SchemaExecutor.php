<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

interface SchemaExecutor
{
    public function execute(string $sql): void;
}