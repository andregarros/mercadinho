<?php

declare(strict_types=1);

use App\Core\Container;

function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = Container::get('config');

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_name(): string
{
    return (string) config('app.name', 'Mercadinho PDV');
}

function asset(string $path): string
{
    return '/public/' . ltrim($path, '/');
}

function auth_check(): bool
{
    return isset($_SESSION['user']);
}

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    $_SESSION['user'] = $user;
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(?string $token): void
{
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_active_path(string $path): bool
{
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return $current === $path;
}
