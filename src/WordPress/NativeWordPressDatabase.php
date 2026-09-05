<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class NativeWordPressDatabase implements WordPressDatabase
{
    public function __construct(
        private readonly object $database,
    ) {
    }

    public function insert(
        string $table,
        array $data,
        array $formats,
    ): int|false {
        return $this->database->insert(
            $table,
            $data,
            $formats
        );
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $dataFormats,
        array $whereFormats,
    ): int|false {
        return $this->database->update(
            $table,
            $data,
            $where,
            $dataFormats,
            $whereFormats
        );
    }

    public function prepare(
        string $query,
        string ...$values,
    ): string {
        return $this->database->prepare(
            $query,
            ...$values
        );
    }

    public function get_row(
        string $query,
    ): ?object {
        return $this->database->get_row($query);
    }

    public function get_charset_collate(): string
    {
        return $this->database->get_charset_collate();
    }

    public function getLastError(): string
    {
        return (string) ($this->database->last_error ?? '');
    }

    public function beginTransaction(): void
    {
        $this->database->query('START TRANSACTION');
    }

    public function commit(): void
    {
        $this->database->query('COMMIT');
    }

    public function rollback(): void
    {
        $this->database->query('ROLLBACK');
    }

    public function get_results(string $query): array
    {
        return $this->database->get_results(
            $query,
            ARRAY_A
        );
    }
}