<?php

declare(strict_types=1);

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
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('mercadinho_sid');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
error_reporting($config['app']['debug'] ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

session_start();

if (empty($_SESSION['bootstrapped_at'])) {
    session_regenerate_id(true);
    $_SESSION['bootstrapped_at'] = time();
}

App\Core\Container::set('config', $config);

App\Services\SchemaManager::ensure();

if (!is_dir(BASE_PATH . '/storage')) {
    mkdir(BASE_PATH . '/storage', 0777, true);
}

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

require BASE_PATH . '/app/Core/helpers.php';

if (!empty($config['app']['force_https']) && !$isHttps && PHP_SAPI !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 302);
    exit;
}
