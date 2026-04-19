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
        $products = $productModel->allByUser((int) auth_user()['id'], trim($_GET['search'] ?? ''));

        $this->view('products/index', ['products' => $products]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $userId = (int) auth_user()['id'];
        $stock = (int) ($_POST['stock'] ?? 0);
        $productModel = new Product();

        try {
            $productModel->create([
                'user_id' => $userId,
                'barcode' => trim($_POST['barcode'] ?? ''),
                'name' => trim($_POST['name'] ?? ''),
                'price' => (float) ($_POST['price'] ?? 0),
                'stock' => $stock,
            ]);

            $product = $productModel->findByBarcode(trim($_POST['barcode'] ?? ''), $userId);
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
            flash('error', 'Não foi possível cadastrar o produto. Verifique se o código de barras já existe.');
            $this->redirect('/products');
        }

        flash('success', 'Produto cadastrado com sucesso.');
        $this->redirect('/products');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        try {
            (new Product())->updateProduct([
                'id' => (int) ($_POST['id'] ?? 0),
                'user_id' => (int) auth_user()['id'],
                'barcode' => trim($_POST['barcode'] ?? ''),
                'name' => trim($_POST['name'] ?? ''),
                'price' => (float) ($_POST['price'] ?? 0),
                'stock' => (int) ($_POST['stock'] ?? 0),
            ]);
        } catch (Throwable $exception) {
            flash('error', 'Não foi possível atualizar o produto. Verifique os dados informados.');
            $this->redirect('/products');
        }

        flash('success', 'Produto atualizado.');
        $this->redirect('/products');
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        (new Product())->deleteProduct((int) ($_POST['id'] ?? 0), (int) auth_user()['id']);
        flash('success', 'Produto removido.');
        $this->redirect('/products');
    }

    public function findByBarcode(): void
    {
        $this->requireAuth();

        $barcode = trim($_GET['barcode'] ?? '');
        $product = (new Product())->findByBarcode($barcode, (int) auth_user()['id']);

        if (!$product) {
            $this->json(['success' => false, 'message' => 'Produto não encontrado.'], 404);
        }

        $this->json(['success' => true, 'product' => $product]);
    }
}
