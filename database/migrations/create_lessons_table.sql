-- Lessons (embedded video; the provider column keeps the player agnostic)
CREATE TABLE IF NOT EXISTS lessons (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id        BIGINT UNSIGNED NOT NULL,
    module_id        BIGINT UNSIGNED NOT NULL,
    title            VARCHAR(180)    NOT NULL,
    description      TEXT            NULL,
    video_url        VARCHAR(500)    NULL,
    video_provider   ENUM('youtube','vimeo','bunny','file','other') NOT NULL DEFAULT 'other'
                     COMMENT 'cached provider; the controller also detects it from the URL',
    thumbnail        VARCHAR(255)    NULL,
    duration_seconds INT UNSIGNED    NOT NULL DEFAULT 0,
    order_index      INT UNSIGNED    NOT NULL DEFAULT 0,
    status           ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lessons_module_order (module_id, order_index),
    KEY idx_lessons_course (course_id),
    KEY idx_lessons_status (status),
    CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
