<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AppSetting extends Model
{
    private const ENCRYPTED_KEYS = [
        'subscription_access_token',
    ];

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM app_settings ORDER BY setting_key');
        $rows = $stmt->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = $this->decodeValue($row);
        }

        return $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare('SELECT * FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $default;
        }

        return $this->decodeValue($row);
    }

    public function set(string $key, mixed $value): void
    {
        $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);
        $storedValue = $this->encodeValue($value, $isEncrypted);

        $stmt = $this->db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, is_encrypted, created_at, updated_at)
             VALUES (:setting_key, :setting_value, :is_encrypted, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                is_encrypted = VALUES(is_encrypted),
                updated_at = NOW()'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $storedValue,
            'is_encrypted' => $isEncrypted ? 1 : 0,
        ]);
    }

    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set((string) $key, $value);
        }
    }

    private function encodeValue(mixed $value, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = is_bool($value)
            ? ($value ? '1' : '0')
            : trim((string) $value);

        if ($encrypted) {
            return encrypt_string($normalized);
        }

        return $normalized;
    }

    private function decodeValue(array $row): ?string
    {
        $value = $row['setting_value'] ?? null;
        if ($value === null) {
            return null;
        }

        if (!empty($row['is_encrypted'])) {
            return decrypt_string((string) $value);
        }

        return (string) $value;
    }
}
