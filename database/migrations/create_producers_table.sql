-- Producers and co-producers
CREATE TABLE IF NOT EXISTS producers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    email           VARCHAR(190)    NOT NULL,
    document        VARCHAR(20)     NOT NULL COMMENT 'Tax ID (CPF or CNPJ), digits only',
    type            ENUM('produtor_principal','coprodutor') NOT NULL,
    gateway         VARCHAR(40)     NOT NULL DEFAULT 'asaas',
    wallet_id       VARCHAR(80)     NULL COMMENT 'walletId / accountId on the gateway',
    account_status  ENUM('pendente','ativo','bloqueado') NOT NULL DEFAULT 'pendente',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_producers_email (email),
    KEY idx_producers_type (type),
    KEY idx_producers_wallet (wallet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
