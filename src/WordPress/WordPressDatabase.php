<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface WordPressDatabase
{
    public function insert(
        string $table,
        array $data,
        array $formats,
    ): int|false;

    public function update(
        string $table,
        array $data,
        array $where,
        array $dataFormats,
        array $whereFormats,
    ): int|false;

    public function prepare(
        string $query,
        string ...$values,
    ): string;

    public function get_row(
        string $query,
    ): ?object;

    public function get_charset_collate(): string;

    public function getLastError(): string;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function get_results(string $query): array;
}