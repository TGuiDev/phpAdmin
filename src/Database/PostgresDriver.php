<?php

declare(strict_types=1);

namespace App\Database;

final class PostgresDriver extends AbstractDriver
{
    protected function buildDsn(): string
    {
        $database = $this->profile->database ?? 'postgres';

        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->profile->host,
            $this->profile->port ?? 5432,
            $database
        );
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function listDatabases(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname"
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function withDatabase(string $database): DriverInterface
    {
        $driver = new self($this->profile->withDatabase($database));
        $driver->connect();

        return $driver;
    }

    public function listTables(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function listColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT column_name AS name, data_type AS type, is_nullable AS nullable, column_default AS default_value
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :table
             ORDER BY ordinal_position"
        );
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll();

        $primaryKeys = $this->getPrimaryKeyColumns($table);

        $columns = [];
        foreach ($rows as $row) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => $row['nullable'] === 'YES',
                'default' => $row['default_value'],
                'is_primary_key' => in_array($row['name'], $primaryKeys, true),
                'extra' => '',
            ];
        }

        return $columns;
    }

    public function getPrimaryKeyColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT kcu.column_name AS name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
             WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = 'public' AND tc.table_name = :table
             ORDER BY kcu.ordinal_position"
        );
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
