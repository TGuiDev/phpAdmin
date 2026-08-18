<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class SqlServerDriver extends AbstractDriver
{
    protected function buildDsn(): string
    {
        $dsn = sprintf('sqlsrv:Server=%s,%d', $this->profile->host, $this->profile->port ?? 1433);
        if ($this->profile->database !== null) {
            $dsn .= ';Database=' . $this->profile->database;
        }

        return $dsn;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    public function listDatabases(): array
    {
        $stmt = $this->pdo()->query('SELECT name FROM sys.databases WHERE database_id > 4 ORDER BY name');

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS default_value
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION'
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
            "SELECT kcu.COLUMN_NAME AS name
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_NAME = kcu.TABLE_NAME
             WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY' AND tc.TABLE_NAME = :table
             ORDER BY kcu.ORDINAL_POSITION"
        );
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function selectRows(
        string $table,
        int $limit,
        int $offset,
        ?string $orderBy = null,
        string $orderDir = 'ASC',
        ?string $whereSql = null
    ): array {
        if ($orderBy === null || $orderBy === '') {
            $primaryKeys = $this->getPrimaryKeyColumns($table);
            $orderBy = $primaryKeys[0] ?? null;
        }

        if ($orderBy === null) {
            $columns = $this->listColumns($table);
            $orderBy = $columns[0]['name'] ?? null;
        }

        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table);
        if ($whereSql !== null && trim($whereSql) !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $this->quoteIdentifier($orderBy) . ' ' . $orderDir;
        }
        $sql .= sprintf(' OFFSET %d ROWS FETCH NEXT %d ROWS ONLY', $offset, $limit);

        $stmt = $this->pdo()->query($sql);
        $rows = $stmt->fetchAll();
        $columnNames = $rows !== [] ? array_keys($rows[0]) : $this->statementColumnNames($stmt);

        return ['columns' => $columnNames, 'rows' => $rows];
    }
}
