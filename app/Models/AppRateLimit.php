<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AppRateLimit extends Model
{
    public function findByKey(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM app_rate_limits WHERE rate_key = :rate_key LIMIT 1');
        $stmt->execute(['rate_key' => $key]);
        return $stmt->fetch() ?: null;
    }

    public function hit(string $key, int $maxAttempts, int $blockMinutes): void
    {
        $record = $this->findByKey($key);
        $now = date('Y-m-d H:i:s');
        $blockedUntil = null;

        if (!$record) {
            $stmt = $this->db->prepare(
                'INSERT INTO app_rate_limits (rate_key, attempts, first_attempt_at, blocked_until, created_at, updated_at)
                 VALUES (:rate_key, 1, :first_attempt_at, NULL, NOW(), NOW())'
            );
            $stmt->execute([
                'rate_key' => $key,
                'first_attempt_at' => $now,
            ]);
            return;
        }

        $attempts = ((int) $record['attempts']) + 1;
        if ($attempts >= $maxAttempts) {
            $blockedUntil = date('Y-m-d H:i:s', strtotime('+' . $blockMinutes . ' minutes'));
        }

        $stmt = $this->db->prepare(
            'UPDATE app_rate_limits
             SET attempts = :attempts,
                 blocked_until = :blocked_until,
                 updated_at = NOW()
             WHERE rate_key = :rate_key'
        );
        $stmt->execute([
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
            'rate_key' => $key,
        ]);
    }

    public function clear(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM app_rate_limits WHERE rate_key = :rate_key');
        $stmt->execute(['rate_key' => $key]);
    }
}
