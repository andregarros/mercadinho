<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\StockMovement;

final class StockController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $userId = (int) auth_user()['id'];
        $productModel = new Product();
        $movementModel = new StockMovement();

        $this->view('stock/index', [
            'products' => $productModel->allByUser($userId),
            'movements' => $movementModel->latestByUser($userId),
            'lowStock' => $productModel->lowStockByUser($userId, (int) config('app.low_stock_threshold', 5)),
        ]);
    }

    public function move(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $userId = (int) auth_user()['id'];
        $productId = (int) ($_POST['product_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'in');
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $note = trim((string) ($_POST['note'] ?? ''));

        if (!in_array($type, ['in', 'out'], true)) {
            flash('error', 'Tipo de movimentacao invalido.');
            $this->redirect('/stock');
        }

        $productModel = new Product();
        $product = $productModel->find($productId, $userId);

        if (!$product) {
            flash('error', 'Produto nao encontrado.');
            $this->redirect('/stock');
        }

        if ($type === 'out' && ((int) $product['stock'] - $quantity) < 0) {
            flash('error', 'Saida maior que o estoque disponivel.');
            $this->redirect('/stock');
        }

        $delta = $type === 'out' ? -$quantity : $quantity;
        $productModel->adjustStock($productId, $userId, $delta);

        (new StockMovement())->create([
            'user_id' => $userId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'note' => mb_substr($note, 0, 255),
        ]);

        flash('success', 'Movimentacao registrada.');
        $this->redirect('/stock');
    }
}
