<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\DriverInterface;
use App\Http\Request;
use App\Support\Flash;
use App\Support\Paginator;

final class TableController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): void
    {
        [$profile, $driver] = $this->openDatabase($params);

        try {
            $tables = $driver->listTables();
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel listar as tabelas: ' . $e->getMessage());
            $this->redirect('/connections/' . $profile->id . '/databases');
        }

        $this->view('tables/index', [
            'title' => 'Tabelas - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'tables' => $tables,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function browse(Request $request, array $params): void
    {
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $columns = $driver->listColumns($params['table']);
        $primaryKeys = $driver->getPrimaryKeyColumns($params['table']);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(200, (int) $request->query('per_page', 25)));
        $orderBy = (string) $request->query('order_by', '');
        $orderDir = strtoupper((string) $request->query('order_dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $whereFilter = trim((string) $request->query('where', ''));

        $columnNames = array_map(static fn (array $c): string => $c['name'], $columns);
        if ($orderBy !== '' && !in_array($orderBy, $columnNames, true)) {
            $orderBy = '';
        }

        try {
            $totalRows = $driver->countRows($params['table'], $whereFilter !== '' ? $whereFilter : null);
            $paginator = new Paginator($page, $perPage, $totalRows);
            $result = $driver->selectRows(
                $params['table'],
                $paginator->perPage,
                $paginator->offset,
                $orderBy !== '' ? $orderBy : null,
                $orderDir,
                $whereFilter !== '' ? $whereFilter : null
            );
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel consultar a tabela: ' . $e->getMessage());
            $this->redirect($baseUrl . '/tables');
        }

        $this->view('tables/browse', [
            'title' => $params['table'] . ' - ' . $params['db'],
            'profile' => $profile,
            'database' => $params['db'],
            'table' => $params['table'],
            'columns' => $columns,
            'primaryKeys' => $primaryKeys,
            'result' => $result,
            'paginator' => $paginator,
            'orderBy' => $orderBy,
            'orderDir' => $orderDir,
            'whereFilter' => $whereFilter,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function structure(Request $request, array $params): void
    {
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $columns = $driver->listColumns($params['table']);
        $primaryKeys = $driver->getPrimaryKeyColumns($params['table']);

        $this->view('tables/structure', [
            'title' => 'Estrutura - ' . $params['table'],
            'profile' => $profile,
            'database' => $params['db'],
            'table' => $params['table'],
            'columns' => $columns,
            'primaryKeys' => $primaryKeys,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function createForm(Request $request, array $params): void
    {
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $columns = $driver->listColumns($params['table']);

        $this->view('tables/form', [
            'title' => 'Novo registro - ' . $params['table'],
            'profile' => $profile,
            'database' => $params['db'],
            'table' => $params['table'],
            'columns' => $columns,
            'mode' => 'create',
            'row' => [],
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function insert(Request $request, array $params): void
    {
        $this->requireCsrf($request);
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $data = $this->extractColumnValues($request, $driver, $params['table']);

        try {
            $driver->insertRow($params['table'], $data);
            Flash::success('Registro inserido com sucesso.');
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel inserir: ' . $e->getMessage());
        }

        $this->redirect($baseUrl . '/tables/' . rawurlencode($params['table']));
    }

    /**
     * @param array<string, string> $params
     */
    public function editForm(Request $request, array $params): void
    {
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $columns = $driver->listColumns($params['table']);
        $primaryKeyValues = $this->primaryKeyFromQuery($request, $driver, $params['table']);

        $row = $driver->findRowByPrimaryKey($params['table'], $primaryKeyValues);
        if ($row === null) {
            Flash::error('Registro nao encontrado.');
            $this->redirect($baseUrl . '/tables/' . rawurlencode($params['table']));
        }

        $this->view('tables/form', [
            'title' => 'Editar registro - ' . $params['table'],
            'profile' => $profile,
            'database' => $params['db'],
            'table' => $params['table'],
            'columns' => $columns,
            'mode' => 'edit',
            'row' => $row,
            'primaryKeyValues' => $primaryKeyValues,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): void
    {
        $this->requireCsrf($request);
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $primaryKeyValues = $this->primaryKeyFromQuery($request, $driver, $params['table']);
        $data = $this->extractColumnValues($request, $driver, $params['table']);

        try {
            $driver->updateRow($params['table'], $data, $primaryKeyValues);
            Flash::success('Registro atualizado com sucesso.');
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel atualizar: ' . $e->getMessage());
        }

        $this->redirect($baseUrl . '/tables/' . rawurlencode($params['table']));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): void
    {
        $this->requireCsrf($request);
        [$profile, $driver, $baseUrl] = $this->openDatabaseForTable($params);

        $primaryKeyValues = $this->primaryKeyFromRequest($request, $driver, $params['table']);

        try {
            $driver->deleteRow($params['table'], $primaryKeyValues);
            Flash::success('Registro removido com sucesso.');
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel remover: ' . $e->getMessage());
        }

        $this->redirect($baseUrl . '/tables/' . rawurlencode($params['table']));
    }

    /**
     * @param array<string, string> $params
     * @return array{0: \App\Database\ConnectionProfile, 1: DriverInterface}
     */
    private function openDatabase(array $params): array
    {
        $profile = $this->requireProfile($params['id']);
        $baseDriver = $this->connectOrRedirect($profile);
        $driver = $this->withDatabaseOrRedirect(
            $baseDriver,
            $params['db'],
            '/connections/' . $profile->id . '/databases'
        );

        return [$profile, $driver];
    }

    /**
     * @param array<string, string> $params
     * @return array{0: \App\Database\ConnectionProfile, 1: DriverInterface, 2: string}
     */
    private function openDatabaseForTable(array $params): array
    {
        [$profile, $driver] = $this->openDatabase($params);
        $baseUrl = '/connections/' . $profile->id . '/databases/' . rawurlencode($params['db']);
        $this->requireTable($driver, $params['table'], $baseUrl . '/tables');

        return [$profile, $driver, $baseUrl];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractColumnValues(Request $request, DriverInterface $driver, string $table): array
    {
        $columns = $driver->listColumns($table);
        $data = [];
        foreach ($columns as $column) {
            $field = 'field_' . $column['name'];
            if ($request->input($field) === null) {
                continue;
            }

            $value = $request->input($field);
            $isEmpty = is_string($value) && $value === '';

            $data[$column['name']] = ($isEmpty && $column['nullable']) ? null : $value;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function primaryKeyFromQuery(Request $request, DriverInterface $driver, string $table): array
    {
        $primaryKeys = $driver->getPrimaryKeyColumns($table);
        $values = [];
        foreach ($primaryKeys as $column) {
            $values[$column] = $request->query('pk_' . $column);
        }

        if ($values === [] || in_array(null, $values, true)) {
            Flash::error('Nao foi possivel identificar a chave primaria do registro.');
            $this->redirect('/');
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function primaryKeyFromRequest(Request $request, DriverInterface $driver, string $table): array
    {
        $primaryKeys = $driver->getPrimaryKeyColumns($table);
        $values = [];
        foreach ($primaryKeys as $column) {
            $values[$column] = $request->input('pk_' . $column);
        }

        if ($values === [] || in_array(null, $values, true)) {
            Flash::error('Nao foi possivel identificar a chave primaria do registro.');
            $this->redirect('/');
        }

        return $values;
    }
}
