<?php

declare(strict_types=1);

namespace App\Database;

interface DriverInterface
{
    /**
     * Abre a conexao PDO usando o perfil informado no construtor.
     * Deve lancar \PDOException em caso de falha.
     */
    public function connect(): void;

    public function isSqlite(): bool;

    /**
     * Lista os "databases" disponiveis no servidor.
     * Para SQLite, retorna um unico item representando o arquivo.
     *
     * @return string[]
     */
    public function listDatabases(): array;

    /**
     * Retorna uma instancia do driver ja conectada em um database especifico.
     * Para SQLite (onde nao existe troca de database), retorna $this.
     */
    public function withDatabase(string $database): DriverInterface;

    /**
     * @return string[]
     */
    public function listTables(): array;

    /**
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, is_primary_key: bool, extra: string}>
     */
    public function listColumns(string $table): array;

    /**
     * @return string[]
     */
    public function getPrimaryKeyColumns(string $table): array;

    public function countRows(string $table, ?string $whereSql = null): int;

    /**
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     */
    public function selectRows(
        string $table,
        int $limit,
        int $offset,
        ?string $orderBy = null,
        string $orderDir = 'ASC',
        ?string $whereSql = null
    ): array;

    /**
     * Busca uma unica linha pela chave primaria, usando parametros ligados (bind).
     *
     * @param array<string, mixed> $primaryKeyValues
     * @return array<string, mixed>|null
     */
    public function findRowByPrimaryKey(string $table, array $primaryKeyValues): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function insertRow(string $table, array $data): void;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $primaryKeyValues
     */
    public function updateRow(string $table, array $data, array $primaryKeyValues): void;

    /**
     * @param array<string, mixed> $primaryKeyValues
     */
    public function deleteRow(string $table, array $primaryKeyValues): void;

    /**
     * Executa uma instrucao SQL arbitraria informada pelo usuario.
     *
     * @return array{columns: string[], rows: array<int, array<string, mixed>>, affected: int, is_select: bool}
     */
    public function executeRaw(string $sql): array;

    public function quoteIdentifier(string $identifier): string;

    public function getLabel(): string;
}
