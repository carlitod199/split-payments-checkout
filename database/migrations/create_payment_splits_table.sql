-- Per-payment revenue split
CREATE TABLE IF NOT EXISTS payment_splits (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id      BIGINT UNSIGNED NOT NULL,
    producer_id     BIGINT UNSIGNED NOT NULL,
    role            ENUM('produtor_principal','coprodutor') NOT NULL,
    percentual      DECIMAL(5,2)    NOT NULL,
    expected_cents  INT UNSIGNED    NOT NULL DEFAULT 0,
    received_cents  INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('previsto','recebido','cancelado') NOT NULL DEFAULT 'previsto',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_splits_payment (payment_id),
    KEY idx_splits_producer (producer_id),
    CONSTRAINT fk_splits_payment  FOREIGN KEY (payment_id)  REFERENCES payments (id)  ON DELETE CASCADE,
    CONSTRAINT fk_splits_producer FOREIGN KEY (producer_id) REFERENCES producers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
