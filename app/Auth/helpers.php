<?php
declare(strict_types=1);
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $u = query('SELECT id,name,email,role,phone,address,active,password_hash FROM users WHERE id=? AND active=1', [$_SESSION['user_id']])->fetch();
    if (!$u || !hash_equals(hash('sha256',$u['password_hash']),$_SESSION['password_version'] ?? '')) {
        unset($_SESSION['user_id'],$_SESSION['password_version']);
        return null;
    }
    unset($u['password_hash']);
    return $u;
}
function require_user(array $roles = []): array {
    $u = current_user(); if (!$u) fail('Please sign in to continue.', 401);
    if ($roles && !in_array($u['role'], $roles, true)) fail('You do not have access to this action.', 403);
    return $u;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf(), $token)) fail('Your session changed. Refresh and try again.', 419);
}
function audit(string $action, string $details = ''): void {
    query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)', [$_SESSION['user_id'] ?? null, $action, mb_substr($details, 0, 500)]);
}
