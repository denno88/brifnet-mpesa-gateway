<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

final class WordPressSchema implements Schema
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
            reference VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            payment_channel VARCHAR(20) NOT NULL,
            merchant_request_id VARCHAR(100) NULL,
            checkout_request_id VARCHAR(100) NULL,
            trans_id VARCHAR(100) NULL,
            bill_ref_number VARCHAR(100) NULL,
            receipt VARCHAR(100) NULL,
            meta LONGTEXT NULL,
            integration_processed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY trans_id (trans_id),
            KEY status_checkout (status, checkout_request_id),
            KEY status_integration (status, integration_processed),
            KEY bill_ref_number (bill_ref_number)
        ) {$charsetCollate};";

        $this->executor->execute($sql);
    }
}