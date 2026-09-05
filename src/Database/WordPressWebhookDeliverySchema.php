<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

final class WordPressWebhookDeliverySchema implements Schema
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
            url VARCHAR(500) NOT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_url (event_id, url),
            KEY status (status)
        ) {$charsetCollate};";

        $this->executor->execute($sql);
    }
}