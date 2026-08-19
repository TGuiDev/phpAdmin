<?php

declare(strict_types=1);

namespace App\Controllers;

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

        $this->view('query/run', [
            'title' => 'Executar SQL - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'sql' => '',
            'result' => null,
            'error' => null,
        ] + $this->sidebarData($profile, null, null, $driver));
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

        $this->view('query/run', [
            'title' => 'Executar SQL - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'sql' => $sql,
            'result' => $result,
            'error' => $error,
        ] + $this->sidebarData($profile, null, null, $driver));
    }
}
