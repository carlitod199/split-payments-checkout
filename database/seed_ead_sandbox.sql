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
