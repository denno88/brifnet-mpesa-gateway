<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressDatabase;
use PDO;

final class MySqlWordPressDatabase implements WordPressDatabase
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function insert(
        string $table,
        array $data,
        array $formats,
    ): int|false {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns})
                VALUES ({$placeholders})";

        $statement = $this->pdo->prepare($sql);

        if (!$statement->execute(array_values($data))) {
            return false;
        }

        return $this->pdo->lastInsertId() !== ''
            ? (int) $this->pdo->lastInsertId()
            : false;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $dataFormats,
        array $whereFormats,
    ): int|false {
        $set = implode(
            ', ',
            array_map(
                fn (string $column): string => "{$column} = ?",
                array_keys($data)
            )
        );

        $conditions = implode(
            ' AND ',
            array_map(
                fn (string $column): string => "{$column} = ?",
                array_keys($where)
            )
        );

        $sql = "UPDATE {$table}
                SET {$set}
                WHERE {$conditions}";

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            ...array_values($data),
            ...array_values($where),
        ]);

        return $statement->rowCount();
    }

    public function prepare(
        string $query,
        string ...$values,
    ): string {
        $index = 0;

        return preg_replace_callback(
            '/%[sd]/',
            function (array $match) use ($values, &$index): string {
                if (!array_key_exists($index, $values)) {
                    throw new \InvalidArgumentException(
                        'Not enough values supplied for query.'
                    );
                }

                $index++;

                return $this->pdo->quote($values[$index - 1]);
            },
            $query,
        ) ?? throw new \RuntimeException(
            'Failed to prepare SQL query.'
        );
    }

    public function get_row(string $query): ?object
    {
        $statement = $this->pdo->query($query);
        $row = $statement->fetch(PDO::FETCH_OBJ);

        return $row === false ? null : $row;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function getLastError(): string
    {
        return '';
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function get_results(string $query): array
    {
        $statement = $this->pdo->query($query);

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }
}