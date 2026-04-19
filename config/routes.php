<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PosController;
use App\Controllers\ProductController;
use App\Controllers\SalesController;
use App\Controllers\StockController;

$router->get('/', [DashboardController::class, 'index']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/products', [ProductController::class, 'index']);
$router->post('/products/store', [ProductController::class, 'store']);
$router->post('/products/update', [ProductController::class, 'update']);
$router->post('/products/delete', [ProductController::class, 'delete']);
$router->get('/products/barcode', [ProductController::class, 'findByBarcode']);

$router->get('/stock', [StockController::class, 'index']);
$router->post('/stock/move', [StockController::class, 'move']);

$router->get('/sales', [SalesController::class, 'index']);
$router->get('/sales/show', [SalesController::class, 'show']);

$router->get('/pos', [PosController::class, 'index']);
$router->get('/pos/product', [PosController::class, 'lookupProduct']);
$router->post('/pos/checkout', [PosController::class, 'checkout']);

$router->get('/manifest.webmanifest', [DashboardController::class, 'manifest']);
$router->get('/service-worker.js', [DashboardController::class, 'serviceWorker']);
