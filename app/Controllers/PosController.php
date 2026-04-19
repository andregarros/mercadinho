<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Sale;
use Throwable;

final class PosController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->view('pos/index');
    }

    public function lookupProduct(): void
    {
        $this->requireAuth();

        $barcode = trim($_GET['barcode'] ?? '');
        $product = (new Product())->findByBarcode($barcode, (int) auth_user()['id']);

        if (!$product) {
            $this->json(['success' => false, 'message' => 'Código não encontrado.'], 404);
        }

        if ((int) $product['stock'] <= 0) {
            $this->json(['success' => false, 'message' => 'Produto sem estoque.'], 422);
        }

        $this->json(['success' => true, 'product' => $product]);
    }

    public function checkout(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan(true);

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        verify_csrf($payload['csrf_token'] ?? null);

        $items = $payload['items'] ?? [];
        $paymentMethod = $payload['payment_method'] ?? 'dinheiro';

        if (empty($items)) {
            $this->json(['success' => false, 'message' => 'Adicione itens ao carrinho.'], 422);
        }

        try {
            $saleId = (new Sale())->createSale((int) auth_user()['id'], $paymentMethod, $items);
            $this->json(['success' => true, 'message' => 'Venda finalizada com sucesso.', 'sale_id' => $saleId]);
        } catch (Throwable $exception) {
            $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
