-- Bridge between the product that was sold (checkout/split) and the course(s)
-- it delivers. Allows a single purchase to unlock a bundle of courses.
CREATE TABLE IF NOT EXISTS product_courses (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  BIGINT UNSIGNED NOT NULL,
    course_id   BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_course (product_id, course_id),
    KEY idx_pc_course (course_id),
    CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_course  FOREIGN KEY (course_id)  REFERENCES courses (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
