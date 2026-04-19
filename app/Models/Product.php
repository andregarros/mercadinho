<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    public function allByUser(int $userId, string $search = ''): array
    {
        $sql = 'SELECT * FROM produtos WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR barcode LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM produtos WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function lowStockByUser(int $userId, int $threshold): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM produtos WHERE user_id = :user_id AND stock <= :threshold ORDER BY stock ASC, name ASC'
        );
        $stmt->execute(['user_id' => $userId, 'threshold' => $threshold]);
        return $stmt->fetchAll();
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO produtos (user_id, barcode, name, price, stock, created_at, updated_at)
             VALUES (:user_id, :barcode, :name, :price, :stock, NOW(), NOW())'
        );

        return $stmt->execute($data);
    }

    public function updateProduct(array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos SET barcode = :barcode, name = :name, price = :price, stock = :stock, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        return $stmt->execute($data);
    }

    public function deleteProduct(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM produtos WHERE id = :id AND user_id = :user_id');
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM produtos WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByBarcode(string $barcode, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM produtos WHERE barcode = :barcode AND user_id = :user_id LIMIT 1');
        $stmt->execute(['barcode' => $barcode, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function adjustStock(int $productId, int $userId, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos SET stock = stock + :quantity, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        return $stmt->execute([
            'quantity' => $quantity,
            'id' => $productId,
            'user_id' => $userId,
        ]);
    }

    public function topSellerByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.name, SUM(iv.quantity) AS total_quantity
             FROM itens_venda iv
             INNER JOIN produtos p ON p.id = iv.product_id
             INNER JOIN vendas v ON v.id = iv.sale_id
             WHERE v.user_id = :user_id
             GROUP BY p.id, p.name
             ORDER BY total_quantity DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }
}
