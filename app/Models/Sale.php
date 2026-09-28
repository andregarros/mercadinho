<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Throwable;

final class Sale extends Model
{
    public function totalsForDashboard(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount END), 0) AS daily_total,
                COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN total_amount END), 0) AS monthly_total
             FROM vendas
             WHERE user_id = :user_id AND payment_status = "paid"'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: ['daily_total' => 0, 'monthly_total' => 0];
    }

    public function totalsForAdminDashboard(): array
    {
        $stmt = $this->db->query(
            'SELECT
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND payment_status = "paid" THEN total_amount END), 0) AS daily_total,
                COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND payment_status = "paid" THEN total_amount END), 0) AS monthly_total,
                COALESCE(SUM(CASE WHEN payment_status = "paid" THEN total_amount END), 0) AS lifetime_total
             FROM vendas'
        );

        return $stmt->fetch() ?: [
            'daily_total' => 0,
            'monthly_total' => 0,
            'lifetime_total' => 0,
        ];
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vendas WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 100');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findWithItems(int $saleId, int $userId): ?array
    {
        $saleStmt = $this->db->prepare('SELECT * FROM vendas WHERE id = :id AND user_id = :user_id LIMIT 1');
        $saleStmt->execute(['id' => $saleId, 'user_id' => $userId]);
        $sale = $saleStmt->fetch();

        if (!$sale) {
            return null;
        }

        $itemsStmt = $this->db->prepare(
            'SELECT iv.*, p.name AS product_name, p.barcode
             FROM itens_venda iv
             INNER JOIN produtos p ON p.id = iv.product_id
             WHERE iv.sale_id = :sale_id'
        );
        $itemsStmt->execute(['sale_id' => $saleId]);
        $sale['items'] = $itemsStmt->fetchAll();

        return $sale;
    }

    public function createSale(int $userId, string $paymentMethod, array $items): int
    {
        $this->db->beginTransaction();

        try {
            $total = 0;

            foreach ($items as $item) {
                $total += ((float) $item['price']) * ((int) $item['quantity']);
            }

            $saleStmt = $this->db->prepare(
                'INSERT INTO vendas (user_id, payment_method, payment_status, total_amount, paid_at, created_at, updated_at)
                 VALUES (:user_id, :payment_method, :payment_status, :total_amount, :paid_at, NOW(), NOW())'
            );
            $isInstantPayment = $paymentMethod !== 'pix';
            $saleStmt->execute([
                'user_id' => $userId,
                'payment_method' => $paymentMethod,
                'payment_status' => $isInstantPayment ? 'paid' : 'pending',
                'total_amount' => $total,
                'paid_at' => $isInstantPayment ? date('Y-m-d H:i:s') : null,
            ]);

            $saleId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO itens_venda (sale_id, product_id, quantity, unit_price, subtotal)
                 VALUES (:sale_id, :product_id, :quantity, :unit_price, :subtotal)'
            );

            $stockStmt = $this->db->prepare(
                'UPDATE produtos SET stock = stock - :quantity, updated_at = NOW()
                 WHERE id = :product_id AND user_id = :user_id AND stock >= :quantity'
            );

            $movementStmt = $this->db->prepare(
                'INSERT INTO estoque_movimentacoes (user_id, product_id, type, quantity, note, created_at)
                 VALUES (:user_id, :product_id, "out", :quantity, :note, NOW())'
            );

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                $price = (float) $item['price'];
                $subtotal = $quantity * $price;

                $itemStmt->execute([
                    'sale_id' => $saleId,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                ]);

                $stockStmt->execute([
                    'quantity' => $quantity,
                    'product_id' => (int) $item['product_id'],
                    'user_id' => $userId,
                ]);

                if ($stockStmt->rowCount() === 0) {
                    throw new \RuntimeException('Estoque insuficiente para concluir a venda.');
                }

                $movementStmt->execute([
                    'user_id' => $userId,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $quantity,
                    'note' => 'Baixa automática da venda #' . $saleId,
                ]);
            }

            $this->db->commit();
            return $saleId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function markAsPaid(int $saleId, string $paymentReference, string $paidAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vendas
             SET payment_status = "paid",
                 payment_reference = :payment_reference,
                 paid_at = :paid_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'payment_reference' => $paymentReference,
            'paid_at' => $paidAt,
            'id' => $saleId,
        ]);
    }
}
