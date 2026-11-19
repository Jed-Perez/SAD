<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
$config = require dirname(__DIR__,2) . '/config.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__,2) . '/storage/php-error.log');
if (PHP_SAPI !== 'cli') {
    $sessionDirectory=dirname(__DIR__,2).'/storage/sessions';
    if(!is_dir($sessionDirectory)) mkdir($sessionDirectory,0700,true);
    session_save_path($sessionDirectory);
    session_name('ncpcs_session');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime','43200');
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}
