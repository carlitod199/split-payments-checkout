-- Per-lesson progress for a student
CREATE TABLE IF NOT EXISTS lesson_progress (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    lesson_id       BIGINT UNSIGNED NOT NULL,
    course_id       BIGINT UNSIGNED NOT NULL,
    watched_seconds INT UNSIGNED    NOT NULL DEFAULT 0,
    completed       TINYINT(1)      NOT NULL DEFAULT 0,
    completed_at    DATETIME        NULL,
    last_watched_at DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_progress_user_lesson (user_id, lesson_id),
    KEY idx_progress_user_course (user_id, course_id),
    CONSTRAINT fk_progress_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
