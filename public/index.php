<?php

declare(strict_types=1);

use App\Controllers\ConnectionController;
use App\Controllers\DatabaseController;
use App\Controllers\QueryController;
use App\Controllers\TableController;
use App\Database\ConnectionManager;
use App\Http\Request;
use App\Http\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$connections = new ConnectionManager();

$connectionController = new ConnectionController($connections);
$databaseController = new DatabaseController($connections);
$tableController = new TableController($connections);
$queryController = new QueryController($connections);

$router = new Router();

$router->get('/', [$connectionController, 'index']);
$router->post('/connections', [$connectionController, 'store']);
$router->post('/connections/{id}/delete', [$connectionController, 'destroy']);

$router->get('/connections/{id}/databases', [$databaseController, 'index']);

$router->get('/connections/{id}/databases/{db}/tables', [$tableController, 'index']);
$router->get('/connections/{id}/databases/{db}/tables/{table}', [$tableController, 'browse']);
$router->get('/connections/{id}/databases/{db}/tables/{table}/structure', [$tableController, 'structure']);
$router->get('/connections/{id}/databases/{db}/tables/{table}/new', [$tableController, 'createForm']);
$router->post('/connections/{id}/databases/{db}/tables/{table}/rows', [$tableController, 'insert']);
$router->get('/connections/{id}/databases/{db}/tables/{table}/edit', [$tableController, 'editForm']);
$router->post('/connections/{id}/databases/{db}/tables/{table}/update', [$tableController, 'update']);
$router->post('/connections/{id}/databases/{db}/tables/{table}/delete', [$tableController, 'delete']);

$router->get('/connections/{id}/databases/{db}/query', [$queryController, 'form']);
$router->post('/connections/{id}/databases/{db}/query', [$queryController, 'run']);

$request = Request::capture();

try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Erro inesperado</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>';
}
