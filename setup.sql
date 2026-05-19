-- ============================================================
-- CRUD App — Setup do Banco de Dados
-- Sistema de Monitoramento de Incêndio (ESP32)
-- Execute no phpMyAdmin ou via MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS projeto_ze
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE projeto_ze;

-- ============================================================
-- Tabela: users
-- ============================================================
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin', 'editor', 'visitor') NOT NULL DEFAULT 'visitor',
    job        VARCHAR(100)  DEFAULT NULL,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role   (role),
    INDEX idx_active (active)
);

-- ============================================================
-- Tabela: posts
-- ============================================================
CREATE TABLE posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(200) NOT NULL,
    slug       VARCHAR(220) NOT NULL,
    content    TEXT,
    status     ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_slug     (slug),
    INDEX      idx_status  (status),
    INDEX      idx_user_id (user_id),

    CONSTRAINT fk_posts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ============================================================
-- Tabela: iot_devices
-- Armazena o estado atual de cada dispositivo ESP32.
-- Novos campos: temperature, alarm, sprinkler
-- ============================================================
DROP TABLE IF EXISTS iot_commands;
DROP TABLE IF EXISTS iot_devices;

CREATE TABLE iot_devices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    device_id   VARCHAR(64)          NOT NULL UNIQUE,   -- ex: "esp32-01"
    button      TINYINT(1)           NOT NULL DEFAULT 0, -- botão de emergência físico
    led         TINYINT(1)           NOT NULL DEFAULT 0, -- estado do alarme (pisca)
    temperature TINYINT UNSIGNED     NOT NULL DEFAULT 0, -- temperatura em °C (0–100)
    alarm       TINYINT(1)           NOT NULL DEFAULT 0, -- 0 = Normal | 1 = Risco de Incêndio
    sprinkler   TINYINT(1)           NOT NULL DEFAULT 0, -- 0 = Inativo | 1 = Ativo
    last_seen   DATETIME             DEFAULT NULL,        -- último contato com o ESP32
    created_at  DATETIME             DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Tabela: iot_commands
-- Fila de comandos pendentes para o ESP32 buscar no próximo POST.
-- Comandos disponíveis:
--   "led"    → liga/desliga LED original (retrocompatibilidade)
--   "reset"  → reseta sistema para estado Normal
--   "alarm"  → força ativação do alarme pelo supervisório
-- ============================================================
CREATE TABLE iot_commands (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    device_id   VARCHAR(64)  NOT NULL,
    command     VARCHAR(64)  NOT NULL,
    value       TINYINT      NOT NULL DEFAULT 0,   -- 0 ou 1
    executed    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_device_pending (device_id, executed)
);

-- Dispositivo padrão para testes
INSERT IGNORE INTO iot_devices (device_id) VALUES ('esp32-01');

-- ============================================================
-- MIGRAÇÃO (para instalações existentes — não re-execute o DROP)
-- Se já tiver a tabela iot_devices e quiser apenas adicionar
-- as novas colunas, rode somente este bloco:
-- ============================================================
-- ALTER TABLE iot_devices
--     ADD COLUMN temperature TINYINT UNSIGNED  NOT NULL DEFAULT 0 AFTER led,
--     ADD COLUMN alarm       TINYINT(1)         NOT NULL DEFAULT 0 AFTER temperature,
--     ADD COLUMN sprinkler   TINYINT(1)         NOT NULL DEFAULT 0 AFTER alarm;
--
-- -- Adicionar novos comandos não exige alteração no schema.
-- ============================================================