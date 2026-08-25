-- Modules of a course
CREATE TABLE IF NOT EXISTS course_modules (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id   BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(180)    NOT NULL,
    description TEXT            NULL,
    order_index INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_modules_course_order (course_id, order_index),
    CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
