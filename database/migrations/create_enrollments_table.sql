-- Enrollments: the student <-> course link, originating from a payment
CREATE TABLE IF NOT EXISTS enrollments (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           BIGINT UNSIGNED NOT NULL,
    course_id         BIGINT UNSIGNED NOT NULL,
    payment_id        BIGINT UNSIGNED NULL COMMENT 'NULL when access was granted manually by an admin',
    status            ENUM('active','pending','cancelled','expired') NOT NULL DEFAULT 'active',
    access_starts_at  DATETIME        NULL,
    access_expires_at DATETIME        NULL COMMENT 'NULL = lifetime access',
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enrollments_user_course (user_id, course_id),
    KEY idx_enrollments_status (status),
    KEY idx_enrollments_payment (payment_id),
    CONSTRAINT fk_enrollments_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course  FOREIGN KEY (course_id)  REFERENCES courses (id)  ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
