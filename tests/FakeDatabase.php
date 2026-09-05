<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressDatabase;

final class FakeDatabase implements WordPressDatabase
{
    public array $inserted = [];

    public array $updated = [];

    public bool $insertShouldFail = false;

    public bool $updateShouldFail = false;

    public array $rows = [];

    public ?string $preparedQuery = null;

    public bool $insertShouldFailAsDuplicate = false;

    public string $lastError = '';

    public function insert(
        string $table,
        array $data,
        array $formats,
    ): int|false {
        if ($this->insertShouldFailAsDuplicate) {
            $this->lastError = 'Duplicate entry';

            return false;
        }

        if ($this->insertShouldFail) {
            $this->lastError = 'Database error';

            return false;
        }

        $this->inserted[] = [
            'table' => $table,
            'data' => $data,
            'formats' => $formats,
        ];

        $this->rows[] = (object) $data;

        return 1;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $dataFormats,
        array $whereFormats,
    ): int|false {
        if ($this->updateShouldFail) {
            return false;
        }

        $this->updated[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'data_formats' => $dataFormats,
            'where_formats' => $whereFormats,
        ];

        foreach ($this->rows as $index => $row) {
            if (
                isset($row->url) &&
                isset($where['url']) &&
                $row->url === $where['url']
            ) {
                foreach ($data as $column => $value) {
                    $row->{$column} = $value;
                }

                $this->rows[$index] = $row;

                break;
            }
        }

        return 1;
    }

    public function prepare(
        string $query,
        string ...$values,
    ): string {
        $this->preparedQuery = $query;

        return $query . '|' . implode('|', $values);
    }

    public function get_row(
        string $query,
    ): ?object {
        $parts = explode('|', $query);

        $values = array_slice($parts, 1);

        if ($values === []) {
            return null;
        }

        if (
            count($values) === 2 &&
            isset($values[0], $values[1])
        ) {
            foreach ($this->rows as $row) {
                if (
                    isset($row->event_id, $row->url) &&
                    $row->event_id === $values[0] &&
                    $row->url === $values[1]
                ) {
                    return $row;
                }
            }

            return null;
        }

        $value = $values[0];

        foreach ($this->rows as $row) {
            if (
                isset($row->checkout_request_id) &&
                $row->checkout_request_id === $value
            ) {
                return $row;
            }

            if (
                isset($row->trans_id) &&
                $row->trans_id === $value
            ) {
                return $row;
            }

            if (
                isset($row->event_id) &&
                $row->event_id === $value
            ) {
                return $row;
            }

            if (
                isset($row->url) &&
                $row->url === $value
            ) {
                return $row;
            }
        }

        return null;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public array $transactionOperations = [];

    public function beginTransaction(): void
    {
        $this->transactionOperations[] = 'begin';
    }

    public function commit(): void
    {
        $this->transactionOperations[] = 'commit';
    }

    public function rollback(): void
    {
        $this->transactionOperations[] = 'rollback';
    }

    public function get_results(
        string $query,
    ): array {
        $parts = explode('|', $query);

        $value = $parts[1] ?? null;

        if ($value === null) {
            return [];
        }

        if (str_contains($query, 'JSON_CONTAINS(events')) {
            $event = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return array_values(
                array_filter(
                    $this->rows,
                    fn (object $row): bool =>
                        isset($row->active, $row->events) &&
                        (bool) $row->active === true &&
                        in_array(
                            $event,
                            json_decode(
                                (string) $row->events,
                                true,
                                512,
                                JSON_THROW_ON_ERROR
                            ),
                            true
                        ),
                )
            );
        }

        return array_values(
            array_filter(
                $this->rows,
                fn (object $row): bool =>
                    isset($row->status) &&
                    $row->status === $value,
            )
        );
    }
}