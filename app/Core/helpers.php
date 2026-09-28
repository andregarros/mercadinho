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
    $baseUrl = config('app.base_url', '/');
    return rtrim($baseUrl, '/') . '/public/' . ltrim($path, '/');
}

function url(string $path): string
{
    $baseUrl = config('app.base_url', '/');
    return rtrim($baseUrl, '/') . $path;
}

function app_public_url(): string
{
    $configured = trim((string) config('app.public_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $scheme = is_https_request() ? 'https' : 'http';
    return $scheme . '://' . $host . rtrim((string) config('app.base_url', '/'), '/');
}

function absolute_url(string $path): string
{
    $base = app_public_url();
    if ($base === '') {
        throw new RuntimeException('Configure APP_PUBLIC_URL com a URL publica do sistema para gerar links de webhook.');
    }

    if (!filter_var($base, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('APP_PUBLIC_URL esta invalida. Use uma URL completa, por exemplo https://seudominio.com/mercadinho');
    }

    $host = parse_url($base, PHP_URL_HOST) ?: '';
    if (in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.local')) {
        throw new RuntimeException('APP_PUBLIC_URL precisa ser publica. Mercado Pago nao aceita localhost como notification_url.');
    }

    return $base . $path;
}

function public_webhook_url_or_message(string $path): string
{
    try {
        return absolute_url($path);
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }
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
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    refresh_session_security();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_regenerate_id(true);
}

function client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        $ip = trim(explode(',', (string) $candidate)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function session_ip_fingerprint(): string
{
    $ip = client_ip();

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return substr($ip, 0, 19);
    }

    $parts = explode('.', $ip);
    return implode('.', array_slice($parts, 0, 3));
}

function refresh_session_security(): void
{
    $_SESSION['session_security'] = [
        'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
        'last_activity_at' => time(),
    ];

    if ((bool) config('app.strict_session_ip', false)) {
        $_SESSION['session_security']['ip_fingerprint'] = session_ip_fingerprint();
    }
}

function validate_session_security(): bool
{
    if (!auth_check()) {
        return true;
    }

    $security = $_SESSION['session_security'] ?? null;
    if (!is_array($security)) {
        return false;
    }

    $timeoutSeconds = max(300, (int) config('app.session_timeout_minutes', 120) * 60);
    $currentUserAgentHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    $lastActivityAt = (int) ($security['last_activity_at'] ?? 0);

    if (!hash_equals((string) ($security['user_agent_hash'] ?? ''), $currentUserAgentHash)) {
        return false;
    }

    if ((bool) config('app.strict_session_ip', false)) {
        $currentIpFingerprint = session_ip_fingerprint();
        if (!hash_equals((string) ($security['ip_fingerprint'] ?? ''), $currentIpFingerprint)) {
            return false;
        }
    }

    if ($lastActivityAt > 0 && (time() - $lastActivityAt) > $timeoutSeconds) {
        return false;
    }

    $_SESSION['session_security']['last_activity_at'] = time();
    return true;
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
        exit('Sessao expirada. Recarregue a pagina e tente novamente.');
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
    $baseUrl = config('app.base_url', '/');

    if ($baseUrl !== '/' && str_starts_with($current, $baseUrl)) {
        $current = substr($current, strlen($baseUrl));
        if ($current === '') {
            $current = '/';
        }
    }

    return $current === $path;
}

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function app_encryption_key(): string
{
    $configuredKey = (string) config('app.encryption_key', '');
    if ($configuredKey !== '') {
        return hash('sha256', $configuredKey, true);
    }

    $fallback = (string) config('db.password', '') . '|' . (string) config('app.base_url', '/') . '|' . app_name();
    return hash('sha256', $fallback, true);
}

function encrypt_string(string $plainText): string
{
    $cipher = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);
    $encrypted = openssl_encrypt($plainText, $cipher, app_encryption_key(), OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        throw new RuntimeException('Nao foi possivel criptografar os dados.');
    }

    return base64_encode($iv . $encrypted);
}

function decrypt_string(string $encryptedText): string
{
    $cipher = 'AES-256-CBC';
    $raw = base64_decode($encryptedText, true);
    if ($raw === false) {
        throw new RuntimeException('Credencial criptografada invalida.');
    }

    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = substr($raw, 0, $ivLength);
    $cipherText = substr($raw, $ivLength);

    $decrypted = openssl_decrypt($cipherText, $cipher, app_encryption_key(), OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        throw new RuntimeException('Nao foi possivel descriptografar os dados.');
    }

    return $decrypted;
}

function gateway_label(string $gateway): string
{
    return match ($gateway) {
        'mercado_pago' => 'Mercado Pago',
        'pagseguro' => 'PagSeguro',
        'asaas' => 'Asaas',
        'stripe' => 'Stripe',
        default => ucfirst(str_replace('_', ' ', $gateway)),
    };
}
