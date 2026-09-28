<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Payments\PaymentManager;
use Throwable;

final class PosController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o scanner e o caixa');
        $this->view('pos/index');
    }

    public function lookupProduct(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o scanner e o caixa', true);

        $barcode = trim((string) ($_GET['barcode'] ?? ''));
        if ($barcode === '' || strlen($barcode) > 80) {
            $this->json(['success' => false, 'message' => 'Codigo invalido.'], 422);
        }

        $product = (new Product())->findByBarcode($barcode, (int) auth_user()['id']);

        if (!$product) {
            $this->json(['success' => false, 'message' => 'Codigo nao encontrado.'], 404);
        }

        if ((int) $product['stock'] <= 0) {
            $this->json(['success' => false, 'message' => 'Produto sem estoque.'], 422);
        }

        $this->json(['success' => true, 'product' => $product]);
    }

    public function checkout(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o scanner e o caixa', true);
        $this->enforceWritablePlan(true);

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'message' => 'Payload invalido.'], 422);
        }

        verify_csrf($payload['csrf_token'] ?? null);

        $items = $this->sanitizeItems($payload['items'] ?? []);
        $paymentMethod = (string) ($payload['payment_method'] ?? 'dinheiro');

        if (!in_array($paymentMethod, ['dinheiro', 'pix', 'cartao'], true)) {
            $this->json(['success' => false, 'message' => 'Forma de pagamento invalida.'], 422);
        }

        if ($items === []) {
            $this->json(['success' => false, 'message' => 'Adicione itens ao carrinho.'], 422);
        }

        try {
            $saleId = (new Sale())->createSale((int) auth_user()['id'], $paymentMethod, $items);
            if ($paymentMethod === 'pix') {
                $payment = (new PaymentManager())->createPixPaymentForSale($saleId, (int) auth_user()['id']);

                $this->json([
                    'success' => true,
                    'message' => 'PIX gerado com sucesso. Aguardando pagamento.',
                    'sale_id' => $saleId,
                    'payment' => $payment,
                ]);
            }

            $this->json(['success' => true, 'message' => 'Venda finalizada com sucesso.', 'sale_id' => $saleId]);
        } catch (Throwable $exception) {
            $message = config('app.debug', false) ? $exception->getMessage() : 'Nao foi possivel finalizar a venda.';
            $this->json(['success' => false, 'message' => $message], 422);
        }
    }

    private function sanitizeItems(array $items): array
    {
        $sanitized = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = round((float) ($item['price'] ?? 0), 2);

            if ($productId <= 0 || $quantity <= 0 || $price < 0) {
                continue;
            }

            $sanitized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
            ];
        }

        return $sanitized;
    }
}
