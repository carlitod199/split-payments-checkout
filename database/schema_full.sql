-- ============================================================================
-- split-payments-checkout - COMPLETE database schema
-- (checkout + course-delivery platform).
-- Assembled from the individual migrations, in the correct execution order.
-- MySQL 5.7+/8.0 - InnoDB - utf8mb4
--
-- Two ways to build the database, both producing an identical schema:
--   php database/setup.php --seed   (idempotent; safe to re-run)
--   mysql <db> < database/schema_full.sql   (one-shot: the ALTER in BLOCK 3
--                                            fails if the file is applied twice)
--
-- To create the database from scratch, uncomment the two lines below:
-- CREATE DATABASE IF NOT EXISTS coproducao_checkout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE coproducao_checkout;
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================================
-- BLOCK 1: CHECKOUT / REVENUE SPLIT ----------------------------------------
-- ============================================================================

-- >>> create_producers_table.sql
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


-- >>> create_products_table.sql
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


-- >>> create_payments_table.sql
-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    internal_id          VARCHAR(40)     NOT NULL COMMENT 'internal id (sent as externalReference to the gateway)',
    external_id          VARCHAR(80)     NULL COMMENT 'charge id on the gateway',
    product_id           BIGINT UNSIGNED NOT NULL,
    customer_name        VARCHAR(150)    NOT NULL,
    customer_email       VARCHAR(190)    NOT NULL,
    customer_phone       VARCHAR(20)     NULL,
    customer_doc         VARCHAR(20)     NOT NULL,
    customer_external_id VARCHAR(80)     NULL COMMENT 'customer id on the gateway',
    gross_cents          INT UNSIGNED    NOT NULL,
    fee_cents            INT UNSIGNED    NOT NULL DEFAULT 0,
    net_cents            INT UNSIGNED    NOT NULL DEFAULT 0,
    method               ENUM('pix','cartao') NOT NULL,
    status               ENUM('pendente','pago','recusado','cancelado','estornado') NOT NULL DEFAULT 'pendente',
    idempotency_key      VARCHAR(80)     NOT NULL,
    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at              DATETIME        NULL,
    updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_internal (internal_id),
    UNIQUE KEY uq_payments_idempotency (idempotency_key),
    KEY idx_payments_external (external_id),
    KEY idx_payments_status (status),
    KEY idx_payments_product (product_id),
    CONSTRAINT fk_payments_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> create_payment_splits_table.sql
-- Per-payment revenue split
CREATE TABLE IF NOT EXISTS payment_splits (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id      BIGINT UNSIGNED NOT NULL,
    producer_id     BIGINT UNSIGNED NOT NULL,
    role            ENUM('produtor_principal','coprodutor') NOT NULL,
    percentual      DECIMAL(5,2)    NOT NULL,
    expected_cents  INT UNSIGNED    NOT NULL DEFAULT 0,
    received_cents  INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('previsto','recebido','cancelado') NOT NULL DEFAULT 'previsto',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_splits_payment (payment_id),
    KEY idx_splits_producer (producer_id),
    CONSTRAINT fk_splits_payment  FOREIGN KEY (payment_id)  REFERENCES payments (id)  ON DELETE CASCADE,
    CONSTRAINT fk_splits_producer FOREIGN KEY (producer_id) REFERENCES producers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> create_payment_webhooks_table.sql
-- Received webhooks (with idempotency)
CREATE TABLE IF NOT EXISTS payment_webhooks (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway          VARCHAR(40)     NOT NULL DEFAULT 'asaas',
    event            VARCHAR(60)     NOT NULL,
    idempotency_key  VARCHAR(120)    NOT NULL COMMENT 'unique hash/id of the event',
    payload          JSON            NOT NULL,
    process_status   ENUM('recebido','processado','erro') NOT NULL DEFAULT 'recebido',
    received_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhooks_idempotency (idempotency_key),
    KEY idx_webhooks_event (event),
    KEY idx_webhooks_status (process_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helper table backing the checkout rate limiter
CREATE TABLE IF NOT EXISTS rate_limits (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rl_key      VARCHAR(120)    NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_key (rl_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- BLOCK 2: COURSE DELIVERY PLATFORM ----------------------------------------
-- ============================================================================

-- >>> create_users_table.sql
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


-- >>> create_students_table.sql
-- Additional student details (1:1 with users)
CREATE TABLE IF NOT EXISTS students (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    phone       VARCHAR(20)     NULL,
    document    VARCHAR(20)     NULL COMMENT 'Tax ID (CPF), digits only',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_user (user_id),
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- >>> create_courses_table.sql
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


-- >>> create_course_modules_table.sql
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


-- >>> create_lessons_table.sql
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


-- >>> create_enrollments_table.sql
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


-- >>> create_lesson_progress_table.sql
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


-- >>> create_password_reset_tokens_table.sql
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


-- >>> create_product_courses_table.sql
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


-- ============================================================================
-- BLOCK 3: SCHEMA ADJUSTMENTS ----------------------------------------------
-- ============================================================================

-- >>> alter_payments_add_access_granted.sql
-- Records when access was granted for this payment.
-- Ensures that redeliveries of the "paid" webhook cannot create a duplicate
-- user, enrollment or e-mail.
ALTER TABLE payments
    ADD COLUMN access_granted_at DATETIME NULL DEFAULT NULL AFTER paid_at;


-- ============================================================================
-- BLOCK 4: DEMO DATA (SEEDS) -----------------------------------------------
-- ============================================================================

-- >>> seed_sandbox.sql
-- Sample data for testing against the sandbox environment.
-- Replace the wallet_id placeholders with the REAL walletId values of your
-- Asaas sandbox accounts before running a charge.
--
-- All names, e-mail addresses and tax IDs below are fictional.

INSERT INTO producers (name, email, document, type, gateway, wallet_id, account_status) VALUES
('John Smith', 'john@example.com', '12345678909', 'produtor_principal', 'asaas', 'REPLACE_WITH_PRINCIPAL_WALLET_ID',  'ativo'),
('Jane Doe',   'jane@example.com', '98765432100', 'coprodutor',         'asaas', 'REPLACE_WITH_COPRODUCER_WALLET_ID', 'ativo');

INSERT INTO products
(name, description, price_cents, status, checkout_slug, principal_producer_id, coproducer_producer_id, principal_percent, coproducer_percent)
VALUES
('Demo Course — Product Fundamentals', 'Lifetime access to the demo course, certificate included.', 10000, 'ativo', 'curso-demo', 1, 2, 85.00, 15.00);


-- >>> seed_ead_sandbox.sql
-- ============================================================================
-- Seed data for the course-delivery platform (demo).
-- Prerequisite: run database/seed_sandbox.sql FIRST
--   (it creates producers id=1,2 and products id=1 with slug 'curso-demo').
-- The video URLs are PLACEHOLDERS — replace them with real embeds in the
-- admin panel. All names and e-mail addresses below are fictional.
-- ============================================================================

-- 1) Course owned by the main producer (producers.id = 1)
INSERT INTO courses (title, slug, description, cover_image, banner_image, producer_id, status) VALUES
('Demo Course — Product Fundamentals',
 'demo-course',
 'From the ground up: fundamentals, hands-on practice and strategies you can apply straight away.',
 'assets/img/demo-course-cover.jpg',
 'assets/img/demo-course-banner.jpg',
 1,
 'published');

SET @course_id = LAST_INSERT_ID();

-- 2) Bridge: the 'curso-demo' product (products.id = 1) unlocks this course
INSERT INTO product_courses (product_id, course_id) VALUES (1, @course_id);

-- 3) Modules (in display order)
INSERT INTO course_modules (course_id, title, description, order_index) VALUES
(@course_id, 'Welcome',             'Introduction and how to get the most out of the course.', 1),
(@course_id, 'Fundamentals',        'The foundation everything else builds on.',               2),
(@course_id, 'Hands-on lessons',    'Practical, step-by-step walkthroughs.',                   3),
(@course_id, 'Advanced strategies', 'Techniques for scaling your results.',                    4),
(@course_id, 'Wrap-up',             'Next steps and your certificate.',                        5);

SET @m_welcome  = (SELECT id FROM course_modules WHERE course_id = @course_id AND order_index = 1);
SET @m_fund     = (SELECT id FROM course_modules WHERE course_id = @course_id AND order_index = 2);
SET @m_practice = (SELECT id FROM course_modules WHERE course_id = @course_id AND order_index = 3);
SET @m_advanced = (SELECT id FROM course_modules WHERE course_id = @course_id AND order_index = 4);
SET @m_wrapup   = (SELECT id FROM course_modules WHERE course_id = @course_id AND order_index = 5);

-- 4) Lessons (PLACEHOLDER videos — the 'other' provider triggers the
--    "simulated" badge in the student UI)
INSERT INTO lessons
(course_id, module_id, title, description, video_url, video_provider, thumbnail, duration_seconds, order_index, status)
VALUES
(@course_id, @m_welcome,  'Lesson 01 — Introduction',    'An overview of the journey ahead.',   'https://example.com/embed/lesson-01', 'other', 'assets/img/lesson-01.jpg', 420,  1, 'published'),
(@course_id, @m_fund,     'Lesson 02 — First steps',     'The essential concepts to start with.','https://example.com/embed/lesson-02', 'other', 'assets/img/lesson-02.jpg', 780,  2, 'published'),
(@course_id, @m_fund,     'Lesson 03 — Initial setup',   'Preparing your environment.',          'https://example.com/embed/lesson-03', 'other', 'assets/img/lesson-03.jpg', 660,  3, 'published'),
(@course_id, @m_practice, 'Lesson 04 — Putting it to work','Applying everything in practice.',   'https://example.com/embed/lesson-04', 'other', 'assets/img/lesson-04.jpg', 1140, 4, 'published'),
(@course_id, @m_wrapup,   'Lesson 05 — Next steps',      'Where to go from here.',               'https://example.com/embed/lesson-05', 'other', 'assets/img/lesson-05.jpg', 540,  5, 'published');

-- 5) Demo student. password_hash stays NULL: the password is set through the
--    token link. Point a test payment's customer_email at this address if you
--    want to exercise the automatic access grant.
INSERT INTO users (name, email, password_hash, role, status) VALUES
('Alex Taylor', 'alex@example.com', NULL, 'student', 'active');

SET @user_id = LAST_INSERT_ID();

INSERT INTO students (user_id, phone, document) VALUES
(@user_id, '11999998888', '12345678909');

-- Active enrollment in the demo course (payment_id NULL = granted manually for the demo)
INSERT INTO enrollments (user_id, course_id, payment_id, status, access_starts_at) VALUES
(@user_id, @course_id, NULL, 'active', NOW());
