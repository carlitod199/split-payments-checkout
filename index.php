<?php
/**
 * Convenience redirect.
 *
 * The recommended setup is to point the DocumentRoot (or the virtual host
 * alias) straight at the /public folder, keeping app/, config/, database/ and
 * storage/ out of reach of the browser.
 *
 * This file exists only for the case where the server is pointed at the
 * project root: it forwards the request into /public.
 */
declare(strict_types=1);

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
header('Location: ' . $base . '/public/checkout.php', true, 302);
exit;
