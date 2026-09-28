<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (store_name, name, email, password, is_admin, plan_status, plan_expires_at, created_at, updated_at)
             VALUES (:store_name, :name, :email, :password, :is_admin, :plan_status, :plan_expires_at, NOW(), NOW())'
        );

        return $stmt->execute([
            'store_name' => trim($data['store_name']),
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_admin' => !empty($data['is_admin']) ? 1 : 0,
            'plan_status' => $data['plan_status'],
            'plan_expires_at' => $data['plan_expires_at'],
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function refreshPlanStatus(int $id): ?array
    {
        $user = $this->findById($id);

        if (!$user) {
            return null;
        }

        $status = $user['plan_status'];
        if ($user['plan_expires_at'] && strtotime((string) $user['plan_expires_at']) < time()) {
            $status = 'expired';
            $stmt = $this->db->prepare('UPDATE usuarios SET plan_status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $id]);
            $user['plan_status'] = $status;
        }

        return $user;
    }

    public function countAdmins(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM usuarios WHERE is_admin = 1');
        return (int) $stmt->fetchColumn();
    }

    public function countAdminsExcluding(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE is_admin = 1 AND id <> :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function allWithStats(): array
    {
        $stmt = $this->db->query(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM produtos p WHERE p.user_id = u.id) AS product_count,
                    (SELECT COUNT(*) FROM vendas v WHERE v.user_id = u.id) AS sales_count,
                    (SELECT COALESCE(SUM(v2.total_amount), 0) FROM vendas v2 WHERE v2.user_id = u.id AND v2.payment_status = "paid") AS revenue_total
             FROM usuarios u
             ORDER BY u.created_at DESC'
        );

        return $stmt->fetchAll();
    }

    public function updateProfileByAdmin(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET store_name = :store_name,
                 name = :name,
                 email = :email,
                 is_admin = :is_admin,
                 plan_status = :plan_status,
                 plan_expires_at = :plan_expires_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'store_name' => trim($data['store_name']),
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'is_admin' => !empty($data['is_admin']) ? 1 : 0,
            'plan_status' => $data['plan_status'],
            'plan_expires_at' => $data['plan_expires_at'],
            'id' => $id,
        ]);
    }

    public function updatePlan(int $id, string $status, ?string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET plan_status = :plan_status,
                 plan_expires_at = :plan_expires_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'plan_status' => $status,
            'plan_expires_at' => $expiresAt,
            'id' => $id,
        ]);
    }

    public function deleteByAdmin(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
