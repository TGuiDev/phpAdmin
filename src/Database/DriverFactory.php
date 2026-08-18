<?php

declare(strict_types=1);

namespace App\Database;

use InvalidArgumentException;

final class DriverFactory
{
    public const MYSQL = 'mysql';
    public const POSTGRES = 'pgsql';
    public const SQLITE = 'sqlite';
    public const SQLSERVER = 'sqlsrv';

    /**
     * @return array<string, string>
     */
    public static function available(): array
    {
        return [
            self::MYSQL => 'MySQL / MariaDB',
            self::POSTGRES => 'PostgreSQL',
            self::SQLITE => 'SQLite',
            self::SQLSERVER => 'SQL Server',
        ];
    }

    public static function make(ConnectionProfile $profile): DriverInterface
    {
        return match ($profile->driver) {
            self::MYSQL => new MySQLDriver($profile),
            self::POSTGRES => new PostgresDriver($profile),
            self::SQLITE => new SQLiteDriver($profile),
            self::SQLSERVER => new SqlServerDriver($profile),
            default => throw new InvalidArgumentException('Tipo de banco desconhecido: ' . $profile->driver),
        };
    }

    public static function defaultPort(string $driver): ?int
    {
        return match ($driver) {
            self::MYSQL => 3306,
            self::POSTGRES => 5432,
            self::SQLSERVER => 1433,
            default => null,
        };
    }
}
