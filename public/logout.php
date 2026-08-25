<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Support\Database;

$config = require __DIR__ . '/../config/payment.php';
Database::init($config['db']);

(new AuthController($config))->handleLogout();
