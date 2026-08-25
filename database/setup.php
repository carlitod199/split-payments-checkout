<?php
declare(strict_types=1);

/**
 * Database installer (CLI only).
 *
 * Usage:
 *   php database/setup.php          -> creates the database and runs every migration
 *   php database/setup.php --seed   -> also inserts the demo data
 *
 * Credentials are read from the same .env the application uses, through
 * config/payment.php.
 *
 * The script is idempotent: every migration is guarded (CREATE TABLE IF NOT
 * EXISTS, and an information_schema check for the ALTER), and each seed file is
 * skipped when its data is already present. Running it twice is a no-op.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$config = require __DIR__ . '/../config/payment.php';
$db = $config['db'];

$dsnServer = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);

try {
    $pdo = new PDO($dsnServer, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the MySQL server: {$e->getMessage()}\n");
    exit(1);
}

// 1. create the database if it does not exist
$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $db['name']
));
$pdo->exec(sprintf('USE `%s`', $db['name']));
echo "Database `{$db['name']}` is ready.\n";

/**
 * Runs one migration file.
 * Every CREATE TABLE in this project is written as CREATE TABLE IF NOT EXISTS,
 * so re-running is safe.
 */
$runMigration = static function (PDO $pdo, string $name): void {
    $file = __DIR__ . "/migrations/{$name}.sql";
    if (!is_file($file)) {
        fwrite(STDERR, "Missing migration: {$name}\n");
        exit(1);
    }
    $pdo->exec((string) file_get_contents($file));
    echo "  migrated: {$name}\n";
};

/** True when $table already has a column called $column. */
$columnExists = static function (PDO $pdo, string $schema, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':s' => $schema, ':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

/** Number of rows matching a simple equality check, 0 when the table is missing. */
$countWhere = static function (PDO $pdo, string $table, string $column, string $value): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :v");
        $stmt->execute([':v' => $value]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
};

// 2. run every migration, in dependency order.
//    Checkout tables first, then the course-delivery tables that reference them.
$order = [
    // --- checkout / revenue split ---
    'create_producers_table',
    'create_products_table',
    'create_payments_table',
    'create_payment_splits_table',
    'create_payment_webhooks_table',   // also creates rate_limits
    // --- course delivery ---
    'create_users_table',
    'create_students_table',
    'create_courses_table',            // FK -> producers
    'create_course_modules_table',     // FK -> courses
    'create_lessons_table',            // FK -> courses, course_modules
    'create_enrollments_table',        // FK -> users, courses, payments
    'create_lesson_progress_table',    // FK -> users, lessons, courses
    'create_password_reset_tokens_table',
    'create_product_courses_table',    // FK -> products, courses
];

foreach ($order as $name) {
    $runMigration($pdo, $name);
}

// 3. ALTER migrations are not self-guarding, so check before applying.
if ($columnExists($pdo, $db['name'], 'payments', 'access_granted_at')) {
    echo "  skipped: alter_payments_add_access_granted (column already present)\n";
} else {
    $runMigration($pdo, 'alter_payments_add_access_granted');
}

// 4. optional seed data. Each file is skipped when its rows already exist, so
//    --seed can be passed repeatedly without duplicating anything.
if (in_array('--seed', $argv, true)) {
    if ($countWhere($pdo, 'products', 'checkout_slug', 'curso-demo') > 0) {
        echo "  skipped: seed_sandbox.sql (demo product already present)\n";
    } else {
        $pdo->exec((string) file_get_contents(__DIR__ . '/seed_sandbox.sql'));
        echo "  seeded: seed_sandbox.sql (remember to replace the placeholder wallet ids)\n";
    }

    if ($countWhere($pdo, 'courses', 'slug', 'demo-course') > 0) {
        echo "  skipped: seed_ead_sandbox.sql (demo course already present)\n";
    } else {
        $pdo->exec((string) file_get_contents(__DIR__ . '/seed_ead_sandbox.sql'));
        echo "  seeded: seed_ead_sandbox.sql\n";
    }
}

echo "Done.\n";
