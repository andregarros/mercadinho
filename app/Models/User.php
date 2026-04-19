<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (store_name, name, email, password, plan_status, plan_expires_at, created_at, updated_at)
             VALUES (:store_name, :name, :email, :password, :plan_status, :plan_expires_at, NOW(), NOW())'
        );

        return $stmt->execute([
            'store_name' => $data['store_name'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'plan_status' => $data['plan_status'],
            'plan_expires_at' => $data['plan_expires_at'],
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
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
        if ($user['plan_expires_at'] && strtotime($user['plan_expires_at']) < time()) {
            $status = 'expired';
            $stmt = $this->db->prepare('UPDATE usuarios SET plan_status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $id]);
            $user['plan_status'] = $status;
        }

        return $user;
    }
}
