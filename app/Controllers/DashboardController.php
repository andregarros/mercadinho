<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $userModel = new User();
        $freshUser = $userModel->refreshPlanStatus((int) auth_user()['id']);
        if ($freshUser) {
            $_SESSION['user'] = $freshUser;
        }

        $productModel = new Product();
        $saleModel = new Sale();
        $userId = (int) auth_user()['id'];
        $threshold = (int) config('app.low_stock_threshold', 5);

        $totals = $saleModel->totalsForDashboard($userId);
        $lowStock = $productModel->lowStockByUser($userId, $threshold);
        $topSeller = $productModel->topSellerByUser($userId);
        $subscription = (new SubscriptionManager())->currentStateForUser($userId);

        $this->view('dashboard/index', [
            'totals' => $totals,
            'productCount' => $productModel->countByUser($userId),
            'lowStock' => $lowStock,
            'topSeller' => $topSeller,
            'threshold' => $threshold,
            'subscription' => $subscription,
        ]);
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $baseUrl = rtrim((string) config('app.base_url', '/'), '/');
        echo json_encode([
            'name' => app_name(),
            'short_name' => 'Mercadinho',
            'start_url' => $baseUrl . '/dashboard',
            'display' => 'standalone',
            'background_color' => '#f5efe2',
            'theme_color' => '#17423c',
            'description' => 'PDV simples para pequenos mercadinhos.',
            'icons' => [
                [
                    'src' => $baseUrl . '/public/assets/icons/icon.svg',
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        readfile(BASE_PATH . '/public/assets/js/service-worker.js');
        exit;
    }
}
