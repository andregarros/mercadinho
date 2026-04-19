<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StockMovement extends Model
{
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO estoque_movimentacoes (user_id, product_id, type, quantity, note, created_at)
             VALUES (:user_id, :product_id, :type, :quantity, :note, NOW())'
        );

        return $stmt->execute($data);
    }

    public function latestByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT em.*, p.name AS product_name
             FROM estoque_movimentacoes em
             INNER JOIN produtos p ON p.id = em.product_id
             WHERE em.user_id = :user_id
             ORDER BY em.created_at DESC
             LIMIT 50'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
