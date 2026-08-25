<?php
declare(strict_types=1);

use App\Support\Database;
use App\Controllers\WebhookPaymentController;
use App\Support\Http;

$config = require __DIR__ . '/../../config/payment.php';
Database::init($config['db']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Http::json(['ok' => false], 405);
}

(new WebhookPaymentController($config))->handle();
