<?php

declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$config = require BASE_PATH . '/config/app.php';

date_default_timezone_set($config['app']['timezone']);

App\Core\Container::set('config', $config);

if (!is_dir(BASE_PATH . '/storage')) {
    mkdir(BASE_PATH . '/storage', 0777, true);
}

require BASE_PATH . '/app/Core/helpers.php';
