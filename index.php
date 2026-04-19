<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$config = App\Core\Container::get('config');

$router = new Router();
$router->setBasePath($config['app']['base_url']);
require __DIR__ . '/config/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
