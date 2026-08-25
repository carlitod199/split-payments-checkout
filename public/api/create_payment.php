<?php
declare(strict_types=1);

use App\Support\Database;
use App\Controllers\CheckoutController;
use App\Support\Http;

$config = require __DIR__ . '/../../config/payment.php';
Database::init($config['db']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Http::json(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

(new CheckoutController($config))->createPayment();
