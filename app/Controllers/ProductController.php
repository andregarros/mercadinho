<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Throwable;

final class ProductController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $productModel = new Product();
        $search = trim((string) ($_GET['search'] ?? ''));
        $products = $productModel->allByUser((int) auth_user()['id'], mb_substr($search, 0, 120));

        $this->view('products/index', ['products' => $products]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o cadastro de produtos');
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $userId = (int) auth_user()['id'];
        $payload = $this->validatedProductPayload($_POST);
        $stock = $payload['stock'];
        $productModel = new Product();

        try {
            $productModel->create([
                'user_id' => $userId,
                'barcode' => $payload['barcode'],
                'name' => $payload['name'],
                'price' => $payload['price'],
                'stock' => $stock,
            ]);

            $product = $productModel->findByBarcode($payload['barcode'], $userId);
            if ($product && $stock > 0) {
                (new StockMovement())->create([
                    'user_id' => $userId,
                    'product_id' => (int) $product['id'],
                    'type' => 'in',
                    'quantity' => $stock,
                    'note' => 'Estoque inicial no cadastro',
                ]);
            }
        } catch (Throwable $exception) {
            flash('error', 'Nao foi possivel cadastrar o produto. Verifique se o codigo de barras ja existe.');
            $this->redirect('/products');
        }

        flash('success', 'Produto cadastrado com sucesso.');
        $this->redirect('/products');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o cadastro de produtos');
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $payload = $this->validatedProductPayload($_POST);

        try {
            (new Product())->updateProduct([
                'id' => (int) ($_POST['id'] ?? 0),
                'user_id' => (int) auth_user()['id'],
                'barcode' => $payload['barcode'],
                'name' => $payload['name'],
                'price' => $payload['price'],
                'stock' => $payload['stock'],
            ]);
        } catch (Throwable $exception) {
            flash('error', 'Nao foi possivel atualizar o produto. Verifique os dados informados.');
            $this->redirect('/products');
        }

        flash('success', 'Produto atualizado.');
        $this->redirect('/products');
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->enforceSubscriptionAccess('o cadastro de produtos');
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        (new Product())->deleteProduct((int) ($_POST['id'] ?? 0), (int) auth_user()['id']);
        flash('success', 'Produto removido.');
        $this->redirect('/products');
    }

    public function findByBarcode(): void
    {
        $this->requireAuth();

        $barcode = trim((string) ($_GET['barcode'] ?? ''));
        if ($barcode === '' || strlen($barcode) > 80) {
            $this->json(['success' => false, 'message' => 'Codigo invalido.'], 422);
        }

        $product = (new Product())->findByBarcode($barcode, (int) auth_user()['id']);

        if (!$product) {
            $this->json(['success' => false, 'message' => 'Produto nao encontrado.'], 404);
        }

        $this->json(['success' => true, 'product' => $product]);
    }

    private function validatedProductPayload(array $input): array
    {
        $barcode = trim((string) ($input['barcode'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $price = (float) ($input['price'] ?? 0);
        $stock = max(0, (int) ($input['stock'] ?? 0));

        if ($barcode === '' || strlen($barcode) > 80 || $name === '' || strlen($name) > 160 || $price < 0) {
            flash('error', 'Dados do produto invalidos.');
            $this->redirect('/products');
        }

        return [
            'barcode' => $barcode,
            'name' => $name,
            'price' => round($price, 2),
            'stock' => $stock,
        ];
    }
}
