<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

final class WordPressWebhookEndpointSchema implements Schema
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
            url VARCHAR(500) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            events LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY active (active)
        ) {$charsetCollate};";

        $this->executor->execute($sql);
    }
}