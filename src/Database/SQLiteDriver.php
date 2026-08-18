<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Para SQLite o "host" do perfil de conexao guarda o caminho do arquivo .sqlite
 * (ou ":memory:"). Nao existe o conceito de multiplos databases num mesmo servidor.
 */
final class SQLiteDriver extends AbstractDriver
{
    public function connect(): void
    {
        $path = $this->profile->host;

        if ($path !== ':memory:' && !is_file($path)) {
            $storageDir = realpath(dirname(__DIR__, 2) . '/storage/sqlite');
            $targetDir = realpath(dirname($path)) ?: null;

            if ($storageDir === false || $targetDir === null || !str_starts_with($targetDir, $storageDir)) {
                throw new RuntimeException(
                    'Arquivo SQLite nao encontrado. Para criar um novo arquivo, salve-o dentro de storage/sqlite/.'
                );
            }
        }

        parent::connect();
    }

    protected function buildDsn(): string
    {
        return 'sqlite:' . $this->profile->host;
    }

    public function isSqlite(): bool
    {
        return true;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function listDatabases(): array
    {
        return [$this->profile->host === ':memory:' ? ':memory:' : basename($this->profile->host)];
    }

    public function withDatabase(string $database): DriverInterface
    {
        return $this;
    }

    public function listTables(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listColumns(string $table): array
    {
        $stmt = $this->pdo()->prepare('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
        $stmt->execute();

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'] !== '' ? $row['type'] : 'TEXT',
                'nullable' => (int) $row['notnull'] === 0,
                'default' => $row['dflt_value'],
                'is_primary_key' => (int) $row['pk'] > 0,
                'extra' => '',
            ];
        }

        return $columns;
    }

    public function getPrimaryKeyColumns(string $table): array
    {
        $columns = $this->listColumns($table);
        $pkColumns = array_filter($columns, static fn (array $c): bool => $c['is_primary_key']);

        return array_values(array_map(static fn (array $c): string => $c['name'], $pkColumns));
    }
}
