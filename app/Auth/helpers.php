<?php
declare(strict_types=1);
require_once __DIR__.'/devices.php';
function current_user(): ?array {
    static $restoreAttempted=false;
    if(empty($_SESSION['user_id'])){
        if($restoreAttempted)return null;$restoreAttempted=true;
        if(!restore_remembered_session())return null;
    }
    if(time()-(int)($_SESSION['last_activity'] ?? time())>1800 || time()-(int)($_SESSION['auth_started'] ?? time())>43200){
        end_auth_session(true);return null;
    }
    $u=query('SELECT id,name,email,role,phone,address,active,password_hash FROM users WHERE id=? AND active=1',[$_SESSION['user_id']])->fetch();
    if(!$u || !hash_equals(hash('sha256',$u['password_hash']),$_SESSION['password_version'] ?? '')){
        end_auth_session(true);return null;
    }
    // Compatible migration of an existing, still-valid PHP session.
    if(empty($_SESSION['device_id'])){
        $_SESSION['device_id']=create_auth_device($u,false);
        $_SESSION['auth_started']=time();$_SESSION['last_activity']=time();
    }
    $device=query('SELECT id FROM auth_devices WHERE id=? AND user_id=? AND password_version=? AND expires_at>NOW()',[$_SESSION['device_id'],$u['id'],$_SESSION['password_version']])->fetchColumn();
    if(!$device){end_auth_session(true);return null;}
    if(auth_foreground_request()){
        $_SESSION['last_activity']=time();
        query('UPDATE auth_devices SET last_seen_at=NOW() WHERE id=? AND last_seen_at<DATE_SUB(NOW(),INTERVAL 1 MINUTE)',[$device]);
    }
    unset($u['password_hash']);return $u;
}
function require_user(array $roles = []): array {
    $u = current_user(); if (!$u) fail(!empty($_SESSION['session_expired'])?'Your session expired or was revoked. Sign in again to continue.':'Please sign in to continue.',401);
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
