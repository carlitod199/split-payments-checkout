-- Courses (the delivery entity; producer_id reuses the checkout's producers table)
CREATE TABLE IF NOT EXISTS courses (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(180)    NOT NULL,
    slug         VARCHAR(160)    NOT NULL,
    description  TEXT            NULL,
    cover_image  VARCHAR(255)    NULL COMMENT 'vertical cover image (card)',
    banner_image VARCHAR(255)    NULL COMMENT 'horizontal banner image (hero)',
    producer_id  BIGINT UNSIGNED NOT NULL,
    status       ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_slug (slug),
    KEY idx_courses_status (status),
    KEY idx_courses_producer (producer_id),
    CONSTRAINT fk_courses_producer FOREIGN KEY (producer_id) REFERENCES producers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
