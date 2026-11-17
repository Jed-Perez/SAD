<?php
declare(strict_types=1);
require_once __DIR__.'/recovery.php';
function handle_guest_auth(string $action,array $data): never {
    if (in_array($action,['forgot_password','reset_password'],true)) handle_password_recovery($action,$data);
    if ($action === 'login' || $action === 'register') {
        $email = strtolower(strval_field($data,'email',190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
        $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $action);
        if (query('SELECT COUNT(*) FROM login_attempts WHERE fingerprint=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$fingerprint])->fetchColumn() >= 15) fail('Too many attempts. Try again in 15 minutes.',429);
        query('INSERT INTO login_attempts(fingerprint) VALUES(?)',[$fingerprint]);
        query('DELETE FROM login_attempts WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
        $password = strval_field($data,'password',200);
        if ($action === 'register') {
            if (strlen($password)<10) fail('Use a password with at least 10 characters.');
            $name = strval_field($data,'name',120);
            query('INSERT INTO users(name,email,password_hash,role,phone,address) VALUES(?,?,?,\'customer\',?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),strval_field($data,'phone',40,false),strval_field($data,'address',500,false)]);
        }
        $u = query('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
        if (!$u || !password_verify($password,$u['password_hash'])) {
            query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$u ? $u['id'] : null,'Failed sign in','Invalid credentials']);
            fail('Email or password is incorrect.',401);
        }
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['password_version']=hash('sha256',$u['password_hash']);
        if($action==='register')audit('Registered account');
        audit('Signed in'); json_out(['user'=>current_user(),'csrf'=>csrf()]);
    }
    fail('Action not found.',404);
}
function handle_account_action(string $action,array $data,array $u): never {
    switch ($action) {
    case 'logout':
        audit('Signed out');
        $_SESSION=[]; session_destroy(); json_out(['ok'=>true]);
    case 'profile':
        $name=strval_field($data,'name',120); $phone=strval_field($data,'phone',40,false); $address=strval_field($data,'address',500,false);
        if (!empty($data['new_password'])) {
            $old=query('SELECT password_hash FROM users WHERE id=?',[$u['id']])->fetchColumn();
            if (!password_verify((string)($data['current_password'] ?? ''),$old)) fail('Current password is incorrect.');
            $pw=strval_field($data,'new_password',200); if(strlen($pw)<10) fail('Use at least 10 characters for the new password.');
            $hash=password_hash($pw,PASSWORD_DEFAULT);
            query('UPDATE users SET password_hash=? WHERE id=?',[$hash,$u['id']]);
            $_SESSION['password_version']=hash('sha256',$hash);
            session_regenerate_id(true);
            audit('Changed password');
        }
        query('UPDATE users SET name=?,phone=?,address=? WHERE id=?',[$name,$phone,$address,$u['id']]);
        audit('Updated profile');
        json_out(['ok'=>true]);
    default: fail('Action not found.',404);
    }
}
