<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SubscriptionPayment extends Model
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscription_payments
                (user_id, gateway, transaction_id, external_reference, amount, plan_days, status, qr_code_image, pix_copy_paste, provider_payload, paid_at, expires_at, created_at, updated_at)
             VALUES
                (:user_id, :gateway, :transaction_id, :external_reference, :amount, :plan_days, :status, :qr_code_image, :pix_copy_paste, :provider_payload, :paid_at, :expires_at, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'gateway' => $data['gateway'],
            'transaction_id' => $data['transaction_id'],
            'external_reference' => $data['external_reference'] ?? null,
            'amount' => $data['amount'],
            'plan_days' => $data['plan_days'],
            'status' => $data['status'] ?? 'pending',
            'qr_code_image' => $data['qr_code_image'] ?? null,
            'pix_copy_paste' => $data['pix_copy_paste'] ?? null,
            'provider_payload' => isset($data['provider_payload']) ? json_encode($data['provider_payload'], JSON_UNESCAPED_UNICODE) : null,
            'paid_at' => $data['paid_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findLatestByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscription_payments WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findPendingByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscription_payments
             WHERE user_id = :user_id AND status = "pending"
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByGatewayAndTransaction(string $gateway, string $transactionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscription_payments WHERE gateway = :gateway AND transaction_id = :transaction_id LIMIT 1'
        );
        $stmt->execute([
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function findByExternalReference(string $gateway, string $externalReference): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscription_payments WHERE gateway = :gateway AND external_reference = :external_reference LIMIT 1'
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
            'UPDATE subscription_payments
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

    public function updateStatusAndExpiry(int $id, string $status, ?string $expiresAt = null, ?string $paidAt = null, ?array $payload = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE subscription_payments
             SET status = :status,
                 expires_at = COALESCE(:expires_at, expires_at),
                 paid_at = :paid_at,
                 provider_payload = COALESCE(:provider_payload, provider_payload),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'expires_at' => $expiresAt,
            'paid_at' => $paidAt,
            'provider_payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'id' => $id,
        ]);
    }

    public function allRecent(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT sp.*, u.store_name, u.name, u.email
             FROM subscription_payments sp
             INNER JOIN usuarios u ON u.id = sp.user_id
             ORDER BY sp.created_at DESC, sp.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalsForAdminDashboard(): array
    {
        $stmt = $this->db->query(
            'SELECT
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = "paid" THEN amount END), 0) AS daily_total,
                COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND status = "paid" THEN amount END), 0) AS monthly_total,
                COALESCE(SUM(CASE WHEN status = "paid" THEN amount END), 0) AS lifetime_total
             FROM subscription_payments'
        );

        return $stmt->fetch() ?: [
            'daily_total' => 0,
            'monthly_total' => 0,
            'lifetime_total' => 0,
        ];
    }
}
