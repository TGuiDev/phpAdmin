<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Support\Flash;

final class DatabaseController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): void
    {
        $profile = $this->requireProfile($params['id']);
        $driver = $this->connectOrRedirect($profile);

        try {
            $databases = $driver->listDatabases();
        } catch (\Throwable $e) {
            Flash::error('Nao foi possivel listar os databases: ' . $e->getMessage());
            $this->redirect('/');
        }

        $this->view('databases/index', [
            'title' => 'Databases - ' . $profile->label,
            'profile' => $profile,
            'databases' => $databases,
        ]);
    }
}
