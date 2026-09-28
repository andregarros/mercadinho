CREATE DATABASE IF NOT EXISTS mercadinho_pdv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mercadinho_pdv;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    plan_status ENUM('trial', 'active', 'expired') NOT NULL DEFAULT 'trial',
    plan_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    barcode VARCHAR(80) NOT NULL,
    name VARCHAR(160) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_user_barcode (user_id, barcode),
    CONSTRAINT fk_produtos_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vendas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    payment_method ENUM('dinheiro', 'pix', 'cartao') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'cancelled') NOT NULL DEFAULT 'paid',
    payment_reference VARCHAR(120) NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_vendas_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS itens_venda (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_itens_venda_sale FOREIGN KEY (sale_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_itens_venda_product FOREIGN KEY (product_id) REFERENCES produtos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_movimentacoes_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_movimentacoes_product FOREIGN KEY (product_id) REFERENCES produtos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    gateway_name ENUM('mercado_pago', 'pagseguro', 'asaas', 'stripe') NOT NULL,
    credentials_encrypted TEXT NOT NULL,
    status ENUM('active', 'inactive', 'error') NOT NULL DEFAULT 'inactive',
    is_connected TINYINT(1) NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_payment_gateway_user_gateway (user_id, gateway_name),
    CONSTRAINT fk_payment_gateways_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sale_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(40) NOT NULL,
    transaction_id VARCHAR(120) NOT NULL,
    external_reference VARCHAR(120) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid', 'expired', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    qr_code_image TEXT NULL,
    pix_copy_paste TEXT NULL,
    provider_payload LONGTEXT NULL,
    paid_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_payments_transaction (gateway, transaction_id),
    KEY idx_payments_sale (sale_id),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES vendas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(40) NOT NULL,
    transaction_id VARCHAR(120) NOT NULL,
    external_reference VARCHAR(120) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    plan_days INT UNSIGNED NOT NULL DEFAULT 30,
    status ENUM('pending', 'paid', 'expired', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
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
);

CREATE TABLE IF NOT EXISTS app_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL,
    setting_value LONGTEXT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_app_settings_key (setting_key)
);

CREATE TABLE IF NOT EXISTS app_rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    first_attempt_at DATETIME NULL,
    blocked_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_app_rate_limits_key (rate_key)
);
