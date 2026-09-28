<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PaymentGateway extends Model
{
    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payment_gateways WHERE user_id = :user_id ORDER BY gateway_name');
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    public function findByUserAndGateway(int $userId, string $gatewayName): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_gateways WHERE user_id = :user_id AND gateway_name = :gateway_name LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'gateway_name' => $gatewayName,
        ]);

        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findActiveByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_gateways
             WHERE user_id = :user_id AND status = "active" AND is_connected = 1
             ORDER BY updated_at DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);

        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function saveGateway(int $userId, string $gatewayName, array $credentials, bool $connected, ?string $errorMessage = null): void
    {
        $existing = $this->findByUserAndGateway($userId, $gatewayName);
        $payload = encrypt_string(json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $status = $connected ? 'active' : 'error';

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE payment_gateways
                 SET credentials_encrypted = :credentials_encrypted,
                     status = :status,
                     is_connected = :is_connected,
                     last_error = :last_error,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'credentials_encrypted' => $payload,
                'status' => $status,
                'is_connected' => $connected ? 1 : 0,
                'last_error' => $errorMessage,
                'id' => $existing['id'],
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO payment_gateways
                (user_id, gateway_name, credentials_encrypted, status, is_connected, last_error, created_at, updated_at)
             VALUES
                (:user_id, :gateway_name, :credentials_encrypted, :status, :is_connected, :last_error, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'gateway_name' => $gatewayName,
            'credentials_encrypted' => $payload,
            'status' => $status,
            'is_connected' => $connected ? 1 : 0,
            'last_error' => $errorMessage,
        ]);
    }

    public function disconnect(int $userId, string $gatewayName): void
    {
        $stmt = $this->db->prepare(
            'UPDATE payment_gateways
             SET status = "inactive", is_connected = 0, last_error = NULL, updated_at = NOW()
             WHERE user_id = :user_id AND gateway_name = :gateway_name'
        );
        $stmt->execute([
            'user_id' => $userId,
            'gateway_name' => $gatewayName,
        ]);
    }

    private function hydrate(array $row): array
    {
        $row['credentials'] = json_decode(decrypt_string((string) $row['credentials_encrypted']), true) ?: [];
        unset($row['credentials_encrypted']);
        return $row;
    }
}
