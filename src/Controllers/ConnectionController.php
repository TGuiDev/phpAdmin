<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\ConnectionManager;
use App\Database\ConnectionProfile;
use App\Database\DriverFactory;
use App\Http\Request;
use App\Support\Flash;

final class ConnectionController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): void
    {
        $this->view('connections/index', [
            'title' => 'Conexoes',
            'connections' => $this->connections->all(),
            'drivers' => DriverFactory::available(),
            'defaultPorts' => [
                DriverFactory::MYSQL => DriverFactory::defaultPort(DriverFactory::MYSQL),
                DriverFactory::POSTGRES => DriverFactory::defaultPort(DriverFactory::POSTGRES),
                DriverFactory::SQLSERVER => DriverFactory::defaultPort(DriverFactory::SQLSERVER),
            ],
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): void
    {
        $this->requireCsrf($request);

        $driver = (string) $request->input('driver', '');
        $label = trim((string) $request->input('label', ''));
        $host = trim((string) $request->input('host', ''));
        $port = $request->input('port', '');
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');
        $database = trim((string) $request->input('database', ''));

        if (!array_key_exists($driver, DriverFactory::available())) {
            Flash::error('Tipo de banco invalido.');
            $this->redirect('/');
        }

        if ($driver === DriverFactory::SQLITE && $host === '') {
            Flash::error('Informe o caminho do arquivo SQLite.');
            $this->redirect('/');
        }

        if ($driver !== DriverFactory::SQLITE && $host === '') {
            Flash::error('Informe o host do servidor.');
            $this->redirect('/');
        }

        $profile = new ConnectionProfile(
            id: ConnectionManager::newId(),
            driver: $driver,
            label: $label !== '' ? $label : ($driver === DriverFactory::SQLITE ? basename($host) : $host),
            host: $host,
            port: $port !== '' ? (int) $port : DriverFactory::defaultPort($driver),
            username: $username,
            password: $password,
            database: $database !== '' ? $database : null
        );

        try {
            $this->connections->driverFor($profile);
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel conectar: ' . $e->getMessage());
            $this->redirect('/');
        }

        $this->connections->add($profile);
        Flash::success('Conexao "' . $profile->label . '" criada com sucesso.');

        if ($driver === DriverFactory::SQLITE) {
            $this->redirect('/connections/' . $profile->id . '/databases/' . rawurlencode($profile->host === ':memory:' ? ':memory:' : basename($profile->host)) . '/tables');
        }

        $this->redirect('/connections/' . $profile->id . '/databases');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): void
    {
        $this->requireCsrf($request);

        $this->connections->remove($params['id']);
        Flash::success('Conexao removida.');
        $this->redirect('/');
    }
}
