<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Mercadinho PDV',
        'base_url' => getenv('APP_BASE_URL') ?: '/mercadinho',
        'public_url' => getenv('APP_PUBLIC_URL') ?: '',
        'timezone' => getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo',
        'low_stock_threshold' => (int) (getenv('APP_LOW_STOCK_THRESHOLD') ?: 5),
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'encryption_key' => getenv('APP_ENCRYPTION_KEY') ?: '',
        'force_https' => filter_var(getenv('APP_FORCE_HTTPS') ?: 'false', FILTER_VALIDATE_BOOL),
        'trial_days' => (int) (getenv('APP_TRIAL_DAYS') ?: 3),
        'subscription_days' => (int) (getenv('APP_SUBSCRIPTION_DAYS') ?: 30),
        'subscription_amount' => (float) (getenv('APP_SUBSCRIPTION_AMOUNT') ?: 29.90),
        'subscription_gateway' => getenv('APP_SUBSCRIPTION_GATEWAY') ?: 'mercado_pago',
        'subscription_access_token' => getenv('APP_SUBSCRIPTION_ACCESS_TOKEN') ?: '',
        'admin_email' => getenv('APP_ADMIN_EMAIL') ?: '',
        'session_timeout_minutes' => (int) (getenv('APP_SESSION_TIMEOUT_MINUTES') ?: 120),
        'strict_session_ip' => filter_var(getenv('APP_STRICT_SESSION_IP') ?: 'false', FILTER_VALIDATE_BOOL),
        'login_max_attempts' => (int) (getenv('APP_LOGIN_MAX_ATTEMPTS') ?: 5),
        'login_block_minutes' => (int) (getenv('APP_LOGIN_BLOCK_MINUTES') ?: 15),
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql.hostinger.com',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'u390542399_mercadinho_pdv',
        'username' => getenv('DB_USERNAME') ?: 'u390542399_root',
        'password' => getenv('DB_PASSWORD') ?: 'G@rros8650',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
