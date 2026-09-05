<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

final class WordPressPaymentEventSchema implements Schema
{
    public function __construct(
        private readonly object $database,
        private readonly SchemaExecutor $executor,
        private readonly string $tableName,
    ) {
    }

    public function install(): void
    {
        $charsetCollate = $this->database->get_charset_collate();

        $sql = "CREATE TABLE {$this->tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id VARCHAR(100) NOT NULL,
            event_name VARCHAR(100) NOT NULL,
            payment_reference VARCHAR(100) NOT NULL,
            payload LONGTEXT NOT NULL,
            occurred_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY payment_reference (payment_reference),
            KEY event_name (event_name),
            KEY occurred_at (occurred_at)
        ) {$charsetCollate};";

        $this->executor->execute($sql);
    }
}