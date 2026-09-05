<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Database\SchemaExecutor;

final class WordPressSchemaExecutor implements SchemaExecutor
{
    public function execute(string $sql): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}