-- Password creation / reset tokens (only the hash is stored)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255)    NOT NULL COMMENT 'hash of the token; the raw value only ever goes into the e-mail',
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reset_token_hash (token_hash),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
