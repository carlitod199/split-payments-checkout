-- Received webhooks (with idempotency)
CREATE TABLE IF NOT EXISTS payment_webhooks (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway          VARCHAR(40)     NOT NULL DEFAULT 'asaas',
    event            VARCHAR(60)     NOT NULL,
    idempotency_key  VARCHAR(120)    NOT NULL COMMENT 'unique hash/id of the event',
    payload          JSON            NOT NULL,
    process_status   ENUM('recebido','processado','erro') NOT NULL DEFAULT 'recebido',
    received_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhooks_idempotency (idempotency_key),
    KEY idx_webhooks_event (event),
    KEY idx_webhooks_status (process_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helper table backing the checkout rate limiter
CREATE TABLE IF NOT EXISTS rate_limits (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rl_key      VARCHAR(120)    NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_key (rl_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
