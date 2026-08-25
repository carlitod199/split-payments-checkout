-- Products sold under a co-production deal (two producers and their split percentages)
CREATE TABLE IF NOT EXISTS products (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                    VARCHAR(180)    NOT NULL,
    description             TEXT            NULL,
    price_cents             INT UNSIGNED    NOT NULL COMMENT 'price in cents',
    status                  ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    checkout_slug           VARCHAR(120)    NOT NULL,
    principal_producer_id   BIGINT UNSIGNED NOT NULL,
    coproducer_producer_id  BIGINT UNSIGNED NOT NULL,
    principal_percent       DECIMAL(5,2)    NOT NULL DEFAULT 85.00,
    coproducer_percent      DECIMAL(5,2)    NOT NULL DEFAULT 15.00,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (checkout_slug),
    KEY idx_products_status (status),
    CONSTRAINT fk_products_principal  FOREIGN KEY (principal_producer_id)  REFERENCES producers (id),
    CONSTRAINT fk_products_coproducer FOREIGN KEY (coproducer_producer_id) REFERENCES producers (id),
    CONSTRAINT chk_products_percent CHECK (principal_percent + coproducer_percent = 100.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
