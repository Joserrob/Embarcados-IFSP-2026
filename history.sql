CREATE TABLE IF NOT EXISTS iot_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    device_id   VARCHAR(64)      NOT NULL,
    temperature TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- °C
    alarm       TINYINT(1)       NOT NULL DEFAULT 0,   -- 0 = Normal | 1 = Risco
    sprinkler   TINYINT(1)       NOT NULL DEFAULT 0,
    recorded_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    -- Índice composto para queries de janela de tempo por dispositivo
    INDEX idx_device_time (device_id, recorded_at)
);

CREATE EVENT IF NOT EXISTS evt_purge_iot_events
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_TIMESTAMP
    DO
        DELETE FROM iot_events
        WHERE recorded_at < NOW() - INTERVAL 30 DAY;
