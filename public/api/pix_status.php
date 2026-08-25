<?php
declare(strict_types=1);

use App\Support\Database;
use App\Controllers\CheckoutController;
use App\Support\Http;
use App\Support\Validator;

$config = require __DIR__ . '/../../config/payment.php';
Database::init($config['db']);

$externalId = Validator::clean($_GET['external_id'] ?? '');
if ($externalId === '') {
    Http::json(['ok' => false, 'error' => 'external_id obrigatório.'], 422);
}

(new CheckoutController($config))->pixStatus($externalId);
