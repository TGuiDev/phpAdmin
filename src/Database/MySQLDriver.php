<?php

declare(strict_types=1);

namespace App\Database;

final class MySQLDriver extends AbstractDriver
{
    protected function buildDsn(): string
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $this->profile->host, $this->profile->port ?? 3306);
        if ($this->profile->database !== null) {
            $dsn .= ';dbname=' . $this->profile->database;
        }

        return $dsn;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function listDatabases(): array
    {
        $stmt = $this->pdo()->query('SHOW DATABASES');

        return array_values(array_filter(
            $stmt->fetchAll(\PDO::FETCH_COLUMN),
            static fn (string $name): bool => !in_array($name, ['information_schema', 'performance_schema', 'mysql', 'sys'], true)
        ));
    }

    public function withDatabase(string $database): DriverInterface
    {
        $driver = new self($this->profile->withDatabase($database));
        $driver->connect();

        return $driver;
    }

    public function listTables(): array
    {
        $stmt = $this->pdo()->query('SHOW TABLES');

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function listColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,
                    COLUMN_DEFAULT AS `default`, COLUMN_KEY AS col_key, EXTRA AS extra
             FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table
             ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute(['schema' => $this->profile->database, 'table' => $table]);

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => $row['nullable'] === 'YES',
                'default' => $row['default'],
                'is_primary_key' => $row['col_key'] === 'PRI',
                'extra' => (string) $row['extra'],
            ];
        }

        return $columns;
    }

    public function getPrimaryKeyColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COLUMN_NAME AS name
             FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table AND COLUMN_KEY = "PRI"
             ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute(['schema' => $this->profile->database, 'table' => $table]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
