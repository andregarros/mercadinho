<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Payment extends Model
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments
                (user_id, sale_id, gateway, transaction_id, external_reference, amount, status, qr_code_image, pix_copy_paste, provider_payload, paid_at, expires_at, created_at, updated_at)
             VALUES
                (:user_id, :sale_id, :gateway, :transaction_id, :external_reference, :amount, :status, :qr_code_image, :pix_copy_paste, :provider_payload, :paid_at, :expires_at, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'sale_id' => $data['sale_id'],
            'gateway' => $data['gateway'],
            'transaction_id' => $data['transaction_id'],
            'external_reference' => $data['external_reference'] ?? null,
            'amount' => $data['amount'],
            'status' => $data['status'] ?? 'pending',
            'qr_code_image' => $data['qr_code_image'] ?? null,
            'pix_copy_paste' => $data['pix_copy_paste'] ?? null,
            'provider_payload' => isset($data['provider_payload']) ? json_encode($data['provider_payload'], JSON_UNESCAPED_UNICODE) : null,
            'paid_at' => $data['paid_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findBySaleId(int $saleId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE sale_id = :sale_id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'sale_id' => $saleId,
            'user_id' => $userId,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function findByGatewayAndTransaction(string $gateway, string $transactionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE gateway = :gateway AND transaction_id = :transaction_id LIMIT 1');
        $stmt->execute([
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function findByExternalReference(string $gateway, string $externalReference): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payments WHERE gateway = :gateway AND external_reference = :external_reference LIMIT 1'
        );
        $stmt->execute([
            'gateway' => $gateway,
            'external_reference' => $externalReference,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status, ?string $paidAt = null, ?array $payload = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE payments
             SET status = :status,
                 paid_at = :paid_at,
                 provider_payload = COALESCE(:provider_payload, provider_payload),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'paid_at' => $paidAt,
            'provider_payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'id' => $id,
        ]);
    }
}
