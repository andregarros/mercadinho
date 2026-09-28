<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Payment;
use App\Models\Sale;

final class SalesController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $sales = (new Sale())->listByUser((int) auth_user()['id']);
        $this->view('sales/index', ['sales' => $sales]);
    }

    public function show(): void
    {
        $this->requireAuth();

        $userId = (int) auth_user()['id'];
        $sale = (new Sale())->findWithItems((int) ($_GET['id'] ?? 0), $userId);
        if (!$sale) {
            flash('error', 'Venda nao encontrada.');
            $this->redirect('/sales');
        }

        $payment = (new Payment())->findBySaleId((int) $sale['id'], $userId);

        $this->view('sales/show', [
            'sale' => $sale,
            'payment' => $payment,
        ]);
    }
}
