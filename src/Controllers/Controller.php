<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\ConnectionManager;
use App\Database\ConnectionProfile;
use App\Database\DriverInterface;
use App\Http\Request;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

abstract class Controller
{
    public function __construct(protected ConnectionManager $connections)
    {
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function requireCsrf(Request $request): void
    {
        if (!Csrf::verify(is_string($request->input('_csrf')) ? $request->input('_csrf') : null)) {
            Flash::error('Sessao expirada ou token invalido. Tente novamente.');
            $this->redirect('/');
        }
    }

    protected function requireProfile(string $connectionId): ConnectionProfile
    {
        $profile = $this->connections->find($connectionId);
        if ($profile === null) {
            Flash::error('Conexao nao encontrada. Ela pode ter expirado da sessao.');
            $this->redirect('/');
        }

        return $profile;
    }

    protected function connectOrRedirect(ConnectionProfile $profile): DriverInterface
    {
        try {
            return $this->connections->driverFor($profile);
        } catch (\Throwable $e) {
            Flash::error('Falha ao conectar: ' . $e->getMessage());
            $this->redirect('/');
        }
    }

    protected function withDatabaseOrRedirect(DriverInterface $driver, string $database, string $fallbackUrl): DriverInterface
    {
        try {
            return $driver->withDatabase($database);
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel abrir o database "' . $database . '": ' . $e->getMessage());
            $this->redirect($fallbackUrl);
        }
    }

    protected function requireTable(DriverInterface $driver, string $table, string $fallbackUrl): void
    {
        try {
            $exists = in_array($table, $driver->listTables(), true);
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel validar a tabela: ' . $e->getMessage());
            $this->redirect($fallbackUrl);
        }

        if (!$exists) {
            Flash::error('Tabela nao encontrada: ' . $table);
            $this->redirect($fallbackUrl);
        }
    }

    /**
     * @return array{connections: array<int, ConnectionProfile>, databases: array<int, string>, tables: array<int, string>}
     */
    protected function sidebarData(
        ?ConnectionProfile $activeProfile = null,
        ?array $databases = null,
        ?array $tables = null,
        ?DriverInterface $driverForListing = null
    ): array {
        if ($databases === null && $activeProfile !== null && $driverForListing !== null) {
            try {
                $databases = $driverForListing->listDatabases();
            } catch (\Throwable $e) {
                $databases = [];
            }
        }

        if ($tables === null && $activeProfile !== null && $driverForListing !== null) {
            try {
                $tables = $driverForListing->listTables();
            } catch (\Throwable $e) {
                $tables = [];
            }
        }

        return [
            'connections' => $this->connections->all(),
            'databases' => $databases ?? [],
            'tables' => $tables ?? [],
        ];
    }
}
