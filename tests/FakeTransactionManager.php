<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\TransactionManager;

final class FakeTransactionManager implements TransactionManager
{
    public array $operations = [];

    public function begin(): void
    {
        $this->operations[] = 'begin';
    }

    public function commit(): void
    {
        $this->operations[] = 'commit';
    }

    public function rollback(): void
    {
        $this->operations[] = 'rollback';
    }
}