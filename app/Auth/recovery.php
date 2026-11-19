<?php
declare(strict_types=1);

function handle_password_recovery(string $action, array $data): never {
    global $config;
    $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '').'|'.$action);
    if (query('SELECT COUNT(*) FROM login_attempts WHERE fingerprint=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$fingerprint])->fetchColumn() >= 10) {
        fail('Too many attempts. Try again in 15 minutes.',429);
    }
    query('INSERT INTO login_attempts(fingerprint) VALUES(?)',[$fingerprint]);
    query('DELETE FROM login_attempts WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');

    if ($action==='forgot_password') {
        $email=strtolower(strval_field($data,'email',190));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
        $base=rtrim((string)$config['app_url'],'/');
        $from=(string)$config['mail_from'];
        if (!filter_var($base,FILTER_VALIDATE_URL) || !in_array(parse_url($base,PHP_URL_SCHEME),['https','http'],true)
            || parse_url($base,PHP_URL_QUERY) || parse_url($base,PHP_URL_FRAGMENT)
            || preg_match('/[\r\n]/',$from) || !filter_var($from,FILTER_VALIDATE_EMAIL)) {
            fail('Password recovery email is not set up yet. Please contact the NCPCS office for help.',503);
        }
        $u=query('SELECT id,email,password_hash FROM users WHERE email=? AND active=1',[$email])->fetch();
        if ($u) {
            // Account-level cooldown also limits mail from requests across different IPs.
            $recent=query('SELECT user_id FROM password_resets WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 MINUTE)',[$u['id']])->fetchColumn();
            if (!$recent) {
                $reset=bin2hex(random_bytes(32));
                $hash=hash('sha256',$reset);
                query('INSERT INTO password_resets(user_id,token_hash,password_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),password_hash=VALUES(password_hash),expires_at=VALUES(expires_at),created_at=NOW()',[$u['id'],$hash,$u['password_hash']]);
                // A fragment keeps the secret out of HTTP access logs and referrers.
                $link=$base.'/portal.php#reset='.$reset;
                $body="A password reset was requested for your TechTrack account.\r\n\r\nOpen this link to choose a new password:\r\n$link\r\n\r\nThis link expires in 30 minutes and can only be used once. If you did not request this, ignore this email.\r\n";
                if (!@mail($u['email'],'Reset your TechTrack password',$body,['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8'])) {
                    query('DELETE FROM password_resets WHERE user_id=? AND token_hash=?',[$u['id'],$hash]);
                    error_log('Password recovery email could not be handed to the mail server.');
                }
            }
        }
        // Same response for unknown, inactive, throttled, and deliverable accounts.
        json_out(['ok'=>true,'message'=>'If an active account matches that email, you will receive a password reset link. Check your inbox and spam folder. If it does not arrive, contact the NCPCS office.']);
    }

    $reset=strval_field($data,'reset_token',64);
    if (!preg_match('/^[a-f0-9]{64}$/D',$reset)) fail('This reset link is invalid or expired. Request a new link.');
    $password=password_field($data,'password',true);
    $confirm=password_field($data,'confirm_password');
    if (strlen($password)<10) fail('Use a password with at least 10 characters.');
    if ($password!==$confirm) fail('The passwords do not match.');
    $newHash=password_hash($password,PASSWORD_DEFAULT);
    $db=db();
    $db->beginTransaction();
    try {
        $r=query('SELECT user_id,password_hash FROM password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE',[hash('sha256',$reset)])->fetch();
        if (!$r) fail('This reset link is invalid or expired. Request a new link.');
        // A password change elsewhere invalidates any previously issued reset link.
        $updated=query('UPDATE users SET password_hash=? WHERE id=? AND active=1 AND password_hash=?',[$newHash,$r['user_id'],$r['password_hash']]);
        if ($updated->rowCount()!==1) fail('This reset link is invalid or expired. Request a new link.');
        query('DELETE FROM password_resets WHERE user_id=?',[$r['user_id']]);
        query('DELETE FROM auth_devices WHERE user_id=?',[$r['user_id']]);
        query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$r['user_id'],'Password reset','Reset using email verification']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    end_auth_session();
    json_out(['ok'=>true,'csrf'=>csrf(),'message'=>'Your password has been reset. Sign in with your new password.']);
}
