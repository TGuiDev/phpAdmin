<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\DriverInterface;
use App\Http\Request;
use App\Support\Flash;

final class QueryController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function form(Request $request, array $params): void
    {
        $profile = $this->requireProfile($params['id']);
        $baseDriver = $this->connectOrRedirect($profile);
        $driver = $this->withDatabaseOrRedirect(
            $baseDriver,
            $params['db'],
            '/connections/' . $profile->id . '/databases'
        );

        $tables = $this->listTablesQuietly($driver);

        $this->view('query/run', [
            'title' => 'Executar SQL - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'sql' => '',
            'result' => null,
            'error' => null,
            'schema' => $this->buildSchema($driver, $tables),
        ] + $this->sidebarData($profile, null, $tables, $driver));
    }

    /**
     * @param array<string, string> $params
     */
    public function run(Request $request, array $params): void
    {
        $this->requireCsrf($request);

        $profile = $this->requireProfile($params['id']);
        $baseDriver = $this->connectOrRedirect($profile);
        $driver = $this->withDatabaseOrRedirect(
            $baseDriver,
            $params['db'],
            '/connections/' . $profile->id . '/databases'
        );

        $sql = trim((string) $request->input('sql', ''));
        $result = null;
        $error = null;

        if ($sql === '') {
            $error = 'Informe uma instrucao SQL.';
        } else {
            try {
                $result = $driver->executeRaw($sql);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $tables = $this->listTablesQuietly($driver);

        $this->view('query/run', [
            'title' => 'Executar SQL - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'sql' => $sql,
            'result' => $result,
            'error' => $error,
            'schema' => $this->buildSchema($driver, $tables),
        ] + $this->sidebarData($profile, null, $tables, $driver));
    }

    /**
     * @return array<int, string>
     */
    private function listTablesQuietly(DriverInterface $driver): array
    {
        try {
            return $driver->listTables();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, array<int, string>>
     */
    private function buildSchema(DriverInterface $driver, array $tables): array
    {
        $schema = [];
        foreach ($tables as $t) {
            try {
                $schema[$t] = array_map(static fn (array $c): string => $c['name'], $driver->listColumns($t));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $schema;
    }
}
