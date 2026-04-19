<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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

        $sale = (new Sale())->findWithItems((int) ($_GET['id'] ?? 0), (int) auth_user()['id']);
        if (!$sale) {
            flash('error', 'Venda não encontrada.');
            $this->redirect('/sales');
        }

        $this->view('sales/show', ['sale' => $sale]);
    }
}
