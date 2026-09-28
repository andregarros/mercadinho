<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SchemaManager
{
    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }

        $db = Database::connection();

        self::ensureUsuariosColumns($db);
        self::ensureSubscriptionsTable($db);
        self::ensureSettingsTable($db);
        self::ensureRateLimitsTable($db);

        self::$ensured = true;
    }

    private static function ensureUsuariosColumns(PDO $db): void
    {
        if (!self::columnExists($db, 'usuarios', 'is_admin')) {
            $db->exec('ALTER TABLE usuarios ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password');
        }
    }

    private static function ensureSubscriptionsTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS subscription_payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                gateway VARCHAR(40) NOT NULL,
                transaction_id VARCHAR(120) NOT NULL,
                external_reference VARCHAR(120) NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                plan_days INT UNSIGNED NOT NULL DEFAULT 30,
                status ENUM("pending", "paid", "expired", "failed", "cancelled") NOT NULL DEFAULT "pending",
                qr_code_image TEXT NULL,
                pix_copy_paste TEXT NULL,
                provider_payload LONGTEXT NULL,
                paid_at DATETIME NULL,
                expires_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_subscription_payments_transaction (gateway, transaction_id),
                KEY idx_subscription_payments_user (user_id),
                CONSTRAINT fk_subscription_payments_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureSettingsTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS app_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL,
                setting_value LONGTEXT NULL,
                is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_app_settings_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureRateLimitsTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS app_rate_limits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rate_key VARCHAR(190) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                first_attempt_at DATETIME NULL,
                blocked_until DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_app_rate_limits_key (rate_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
