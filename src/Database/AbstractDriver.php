<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

abstract class AbstractDriver implements DriverInterface
{
    protected ?PDO $pdo = null;

    public function __construct(protected ConnectionProfile $profile)
    {
    }

    abstract protected function buildDsn(): string;

    abstract public function quoteIdentifier(string $identifier): string;

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $this->pdo = new PDO(
            $this->buildDsn(),
            $this->profile->username !== '' ? $this->profile->username : null,
            $this->profile->password !== '' ? $this->profile->password : null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    protected function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        if ($this->pdo === null) {
            throw new RuntimeException('Conexao nao estabelecida.');
        }

        return $this->pdo;
    }

    public function isSqlite(): bool
    {
        return false;
    }

    public function countRows(string $table, ?string $whereSql = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table);
        if ($whereSql !== null && trim($whereSql) !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $stmt = $this->pdo()->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function selectRows(
        string $table,
        int $limit,
        int $offset,
        ?string $orderBy = null,
        string $orderDir = 'ASC',
        ?string $whereSql = null
    ): array {
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table);
        if ($whereSql !== null && trim($whereSql) !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        if ($orderBy !== null && $orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->quoteIdentifier($orderBy) . ' ' . $orderDir;
        }
        $sql .= $this->buildLimitOffsetClause($limit, $offset);

        $stmt = $this->pdo()->query($sql);
        $rows = $stmt->fetchAll();
        $columns = $rows !== [] ? array_keys($rows[0]) : $this->statementColumnNames($stmt);

        return ['columns' => $columns, 'rows' => $rows];
    }

    protected function buildLimitOffsetClause(int $limit, int $offset): string
    {
        return sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
    }

    /**
     * @return string[]
     */
    protected function statementColumnNames(\PDOStatement $stmt): array
    {
        $columns = [];
        $count = $stmt->columnCount();
        for ($i = 0; $i < $count; $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = $meta['name'] ?? ('col_' . $i);
        }

        return $columns;
    }

    public function findRowByPrimaryKey(string $table, array $primaryKeyValues): ?array
    {
        if ($primaryKeyValues === []) {
            return null;
        }

        $whereParts = [];
        foreach (array_keys($primaryKeyValues) as $column) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = :pk_' . $column;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->pdo()->prepare($sql);
        foreach ($primaryKeyValues as $column => $value) {
            $stmt->bindValue(':pk_' . $column, $value);
        }
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insertRow(string $table, array $data): void
    {
        if ($data === []) {
            throw new RuntimeException('Nenhum valor informado para insercao.');
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':set_' . $c, $columns);
        $quotedColumns = array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo()->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':set_' . $column, $value);
        }
        $stmt->execute();
    }

    public function updateRow(string $table, array $data, array $primaryKeyValues): void
    {
        if ($data === []) {
            throw new RuntimeException('Nenhum valor informado para atualizacao.');
        }
        if ($primaryKeyValues === []) {
            throw new RuntimeException('Nao foi possivel identificar a chave primaria da linha.');
        }

        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
        }

        $whereParts = [];
        foreach (array_keys($primaryKeyValues) as $column) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = :pk_' . $column;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->pdo()->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':set_' . $column, $value);
        }
        foreach ($primaryKeyValues as $column => $value) {
            $stmt->bindValue(':pk_' . $column, $value);
        }
        $stmt->execute();
    }

    public function deleteRow(string $table, array $primaryKeyValues): void
    {
        if ($primaryKeyValues === []) {
            throw new RuntimeException('Nao foi possivel identificar a chave primaria da linha.');
        }

        $whereParts = [];
        foreach (array_keys($primaryKeyValues) as $column) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = :pk_' . $column;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->pdo()->prepare($sql);
        foreach ($primaryKeyValues as $column => $value) {
            $stmt->bindValue(':pk_' . $column, $value);
        }
        $stmt->execute();
    }

    public function executeRaw(string $sql): array
    {
        $statements = array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== '');
        if (count($statements) > 1) {
            throw new RuntimeException('Execute apenas uma instrucao SQL por vez.');
        }

        $stmt = $this->pdo()->query($sql);

        $isSelect = $stmt->columnCount() > 0;

        if ($isSelect) {
            $rows = $stmt->fetchAll();
            $columns = $rows !== [] ? array_keys($rows[0]) : $this->statementColumnNames($stmt);

            return ['columns' => $columns, 'rows' => $rows, 'affected' => count($rows), 'is_select' => true];
        }

        return ['columns' => [], 'rows' => [], 'affected' => $stmt->rowCount(), 'is_select' => false];
    }

    public function getLabel(): string
    {
        return $this->profile->label;
    }
}
