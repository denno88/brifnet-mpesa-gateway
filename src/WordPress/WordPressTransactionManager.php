<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Application\TransactionManager;

final class WordPressTransactionManager implements TransactionManager
{
    public function __construct(
        private readonly WordPressDatabase $database,
    ) {
    }

    public function begin(): void
    {
        $this->database->beginTransaction();
    }

    public function commit(): void
    {
        $this->database->commit();
    }

    public function rollback(): void
    {
        $this->database->rollback();
    }
}