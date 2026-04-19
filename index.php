<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$basePath = '/mercadinho';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($basePath !== '/' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}

$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$config = App\Core\Container::get('config');

$router = new Router();
$router->setBasePath($config['app']['base_url']);
require __DIR__ . '/config/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
