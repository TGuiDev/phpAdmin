<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Representa os dados necessarios para abrir uma conexao.
 * Guardado apenas na sessao do usuario (nunca persistido em disco).
 */
final class ConnectionProfile
{
    public function __construct(
        public readonly string $id,
        public readonly string $driver,
        public readonly string $label,
        public readonly string $host,
        public readonly ?int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $database = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver,
            'label' => $this->label,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            driver: (string) $data['driver'],
            label: (string) $data['label'],
            host: (string) $data['host'],
            port: $data['port'] !== null && $data['port'] !== '' ? (int) $data['port'] : null,
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            database: isset($data['database']) && $data['database'] !== '' ? (string) $data['database'] : null
        );
    }

    public function withDatabase(string $database): self
    {
        return new self(
            $this->id,
            $this->driver,
            $this->label,
            $this->host,
            $this->port,
            $this->username,
            $this->password,
            $database
        );
    }
}
