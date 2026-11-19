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
        $password=password_field($data,'password',$action==='register');
        $remember=integer_field($data,'remember_me',0,1)===1;
        if ($action === 'register') {
            if($password!==password_field($data,'confirm_password'))fail('The passwords do not match.');
            $name = strval_field($data,'name',120);
            query('INSERT INTO users(name,email,password_hash,role,phone,address) VALUES(?,?,?,\'customer\',?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),strval_field($data,'phone',40,false),strval_field($data,'address',500,false)]);
        }
        $u = query('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
        if (!$u || !verify_account_password($password,$u['password_hash'])) {
            query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$u ? $u['id'] : null,'Failed sign in','Invalid credentials']);
            fail('Email or password is incorrect.',401);
        }
        sign_in_account($u,$remember);
        if($action==='register')audit('Registered account');
        audit('Signed in'); json_out(['user'=>current_user(),'csrf'=>csrf()]);
    }
    fail('Action not found.',404);
}
function handle_account_action(string $action,array $data,array $u): never {
    switch ($action) {
    case 'logout':
        audit('Signed out');
        end_auth_session(); json_out(['ok'=>true,'csrf'=>csrf()]);
    case 'profile':
        $name=strval_field($data,'name',120); $phone=strval_field($data,'phone',40,false); $address=strval_field($data,'address',500,false);
        if (!empty($data['new_password'])) {
            $old=query('SELECT password_hash FROM users WHERE id=?',[$u['id']])->fetchColumn();
            if (!verify_account_password(password_field($data,'current_password'),$old)) fail('Current password is incorrect.');
            $pw=password_field($data,'new_password',true);
            if($pw!==password_field($data,'confirm_new_password'))fail('The new passwords do not match.');
            $hash=password_hash($pw,PASSWORD_DEFAULT);
            db()->beginTransaction();
            if(query('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?',[$hash,$u['id'],$old])->rowCount()!==1)fail('Your account changed. Sign in again before changing your password.',409);
            query('DELETE FROM auth_devices WHERE user_id=?',[$u['id']]);
            query('DELETE FROM password_resets WHERE user_id=?',[$u['id']]);
            db()->commit();
            sign_in_account([...$u,'password_hash'=>$hash],false);
            audit('Changed password');notify_user((int)$u['id'],'security','Password changed','Your password was changed. Contact the office if you did not make this change.','dashboard','password:'.bin2hex(random_bytes(12)));
        }
        query('UPDATE users SET name=?,phone=?,address=? WHERE id=?',[$name,$phone,$address,$u['id']]);
        audit('Updated profile');
        json_out(['ok'=>true,'csrf'=>csrf()]);
    default: fail('Action not found.',404);
    }
}
