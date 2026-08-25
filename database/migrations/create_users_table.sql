-- Platform users (admin, producer, student)
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    email           VARCHAR(190)    NOT NULL,
    password_hash   VARCHAR(255)    NULL COMMENT 'NULL until the student sets a password through the token link',
    role            ENUM('admin','producer','student') NOT NULL DEFAULT 'student',
    status          ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
