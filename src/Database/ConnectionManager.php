<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Guarda os perfis de conexao cadastrados apenas na sessao do usuario logado no navegador.
 * Nada e persistido em disco/banco proprio.
 */
final class ConnectionManager
{
    private const SESSION_KEY = 'connections';

    public function all(): array
    {
        $profiles = [];
        foreach ($_SESSION[self::SESSION_KEY] ?? [] as $data) {
            $profiles[] = ConnectionProfile::fromArray($data);
        }

        return $profiles;
    }

    public function find(string $id): ?ConnectionProfile
    {
        $data = $_SESSION[self::SESSION_KEY][$id] ?? null;

        return $data !== null ? ConnectionProfile::fromArray($data) : null;
    }

    public function add(ConnectionProfile $profile): void
    {
        $_SESSION[self::SESSION_KEY][$profile->id] = $profile->toArray();
    }

    public function remove(string $id): void
    {
        unset($_SESSION[self::SESSION_KEY][$id]);
    }

    public function driverFor(ConnectionProfile $profile): DriverInterface
    {
        $driver = DriverFactory::make($profile);
        $driver->connect();

        return $driver;
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
