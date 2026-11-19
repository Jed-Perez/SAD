<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/Auth/google.php';
header('Referrer-Policy: no-referrer');
try {
    if ($_SERVER['REQUEST_METHOD']!=='GET') fail('Use the Continue with Google button to sign in.',405);
    $pending=google_consume_state($_GET['state'] ?? null);
    if (current_user()) fail('You are already signed in. Sign out before switching accounts.',409);
    if (isset($_GET['error'])) fail('Google sign-in was cancelled or declined. Please try again or use email and password.');
    $code=$_GET['code'] ?? null;
    if (!is_string($code) || $code==='' || strlen($code)>4096) fail('Google did not return a sign-in code. Please try again.');
    $identity=google_exchange_code($code,$pending);
    $user=google_find_or_create_user($identity);
    sign_in_account($user,!empty($pending['remember']));
    unset($_SESSION['google_auth_error']);
    audit('Signed in','Using Google');
} catch (AppError $e) {
    $_SESSION['google_auth_error']=$e->getMessage();
} catch (Throwable $e) {
    // Do not log OAuth codes, tokens or credentials, including exception arguments.
    error_log('Google sign-in failed ('.get_class($e).'). Check database migrations and server configuration.');
    $_SESSION['google_auth_error']='Google sign-in could not be completed. Please try again or use email and password.';
}
header('Location: portal.php',true,303);
exit;
