<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Mercadinho PDV',
        'base_url' => '/',
        'timezone' => 'America/Sao_Paulo',
        'low_stock_threshold' => 5,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'mercadinho_pdv',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
