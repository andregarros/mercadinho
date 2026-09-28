<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\DashboardController;
use App\Controllers\PaymentController;
use App\Controllers\PosController;
use App\Controllers\ProductController;
use App\Controllers\SalesController;
use App\Controllers\StockController;
use App\Controllers\SubscriptionController;

$router->get('/', [DashboardController::class, 'index']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/subscription', [SubscriptionController::class, 'show']);
$router->post('/subscription/pix', [SubscriptionController::class, 'createPix']);
$router->get('/subscription/status', [SubscriptionController::class, 'status']);
$router->get('/subscription/webhook/mercado-pago', [SubscriptionController::class, 'webhookMercadoPago']);
$router->post('/subscription/webhook/mercado-pago', [SubscriptionController::class, 'webhookMercadoPago']);

$router->get('/admin', [AdminController::class, 'index']);
$router->post('/admin/settings/update', [AdminController::class, 'updateSettings']);
$router->post('/admin/users/update', [AdminController::class, 'updateUser']);
$router->post('/admin/users/delete', [AdminController::class, 'deleteUser']);

$router->get('/products', [ProductController::class, 'index']);
$router->post('/products/store', [ProductController::class, 'store']);
$router->post('/products/update', [ProductController::class, 'update']);
$router->post('/products/delete', [ProductController::class, 'delete']);
$router->get('/products/barcode', [ProductController::class, 'findByBarcode']);

$router->get('/stock', [StockController::class, 'index']);
$router->post('/stock/move', [StockController::class, 'move']);

$router->get('/sales', [SalesController::class, 'index']);
$router->get('/sales/show', [SalesController::class, 'show']);

$router->get('/payments', [PaymentController::class, 'index']);
$router->post('/payments/connect', [PaymentController::class, 'connect']);
$router->post('/payments/disconnect', [PaymentController::class, 'disconnect']);
$router->get('/payments/status', [PaymentController::class, 'status']);
$router->get('/payments/webhook/mercado-pago', [PaymentController::class, 'webhookMercadoPago']);
$router->post('/payments/webhook/mercado-pago', [PaymentController::class, 'webhookMercadoPago']);
$router->get('/payments/webhook/pagseguro', [PaymentController::class, 'webhookPagSeguro']);
$router->post('/payments/webhook/pagseguro', [PaymentController::class, 'webhookPagSeguro']);
$router->get('/payments/webhook/asaas', [PaymentController::class, 'webhookAsaas']);
$router->post('/payments/webhook/asaas', [PaymentController::class, 'webhookAsaas']);
$router->get('/payments/webhook/stripe', [PaymentController::class, 'webhookStripe']);
$router->post('/payments/webhook/stripe', [PaymentController::class, 'webhookStripe']);

$router->get('/pos', [PosController::class, 'index']);
$router->get('/pos/product', [PosController::class, 'lookupProduct']);
$router->post('/pos/checkout', [PosController::class, 'checkout']);

$router->get('/manifest.webmanifest', [DashboardController::class, 'manifest']);
$router->get('/service-worker.js', [DashboardController::class, 'serviceWorker']);
