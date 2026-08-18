<?php

declare(strict_types=1);

/**
 * Router usado apenas com o servidor embutido do PHP em desenvolvimento:
 *   php -S localhost:8000 -t public public/router.php
 *
 * Serve arquivos estaticos existentes diretamente; qualquer outra rota
 * cai no front controller (index.php).
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
