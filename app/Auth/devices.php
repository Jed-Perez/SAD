<?php
declare(strict_types=1);

function auth_cookie_name(): string { return 'ncpcs_remember'; }
function auth_cookie_path(): string {
    $path=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $path==='/' || $path==='.' ? '/' : rtrim($path,'/').'/';
}
function set_remember_cookie(string $value,int $expires): void {
    if (PHP_SAPI!=='cli') setcookie(auth_cookie_name(),$value,[
        'expires'=>$expires,'path'=>auth_cookie_path(),'httponly'=>true,
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax',
    ]);
    if($value==='')unset($_COOKIE[auth_cookie_name()]);else $_COOKIE[auth_cookie_name()]=$value;
}
function clear_remember_cookie(): void { set_remember_cookie('',time()-3600); }
function device_label(): string {
    $agent=$_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser=str_contains($agent,'Edg/')?'Edge':(str_contains($agent,'Firefox/')?'Firefox':(str_contains($agent,'Chrome/')?'Chrome':(str_contains($agent,'Safari/')?'Safari':'Browser')));
    $platform=str_contains($agent,'Android')?'Android':(preg_match('/iPhone|iPad/',$agent)?'iOS':(str_contains($agent,'Windows')?'Windows':(str_contains($agent,'Macintosh')?'macOS':(str_contains($agent,'Linux')?'Linux':'device'))));
    return $browser.' on '.$platform;
}
function revoke_cookie_device(): void {
    $cookie=$_COOKIE[auth_cookie_name()] ?? '';
    if (is_string($cookie) && preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D',$cookie,$match)) {
        query('DELETE FROM auth_devices WHERE selector_hash=? AND (validator_hash=? OR previous_validator_hash=?)',[hash('sha256',$match[1]),hash('sha256',$match[2]),hash('sha256',$match[2])]);
    }
    clear_remember_cookie();
}
function create_auth_device(array $user,bool $remember): int {
    query('DELETE FROM auth_devices WHERE expires_at<=NOW()');
    $selector=$remember?bin2hex(random_bytes(16)):null;
    $validator=$remember?bin2hex(random_bytes(32)):null;
    $expires=time()+($remember?30*86400:12*3600);
    query('INSERT INTO auth_devices(user_id,selector_hash,validator_hash,password_version,remembered,device_label,expires_at) VALUES(?,?,?,?,?,?,?)',[
        $user['id'],$selector?hash('sha256',$selector):null,$validator?hash('sha256',$validator):null,
        hash('sha256',$user['password_hash']),(int)$remember,device_label(),date('Y-m-d H:i:s',$expires),
    ]);
    $id=(int)db()->lastInsertId();
    if($remember)set_remember_cookie($selector.':'.$validator,$expires);else clear_remember_cookie();
    return $id;
}
function establish_auth_session(array $user,int $deviceId): void {
    if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);
    $_SESSION['user_id']=$user['id'];$_SESSION['device_id']=$deviceId;
    $_SESSION['password_version']=hash('sha256',$user['password_hash']);
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    $_SESSION['auth_started']=time();$_SESSION['last_activity']=time();
    unset($_SESSION['session_expired'],$_SESSION['google_auth']);
}
function sign_in_account(array $user,bool $remember=false): void {
    revoke_cookie_device();
    if(!empty($_SESSION['device_id']))query('DELETE FROM auth_devices WHERE id=? AND user_id=?',[$_SESSION['device_id'],$_SESSION['user_id'] ?? 0]);
    establish_auth_session($user,create_auth_device($user,$remember));
    notify_user((int)$user['id'],'security','New sign-in','Your account was signed in on '.device_label().'. Review your signed-in devices if this was unexpected.','dashboard','signin:'.$_SESSION['device_id']);
}
function end_auth_session(bool $expired=false): void {
    if(!empty($_SESSION['device_id']))query('DELETE FROM auth_devices WHERE id=? AND user_id=?',[$_SESSION['device_id'],$_SESSION['user_id'] ?? 0]);
    revoke_cookie_device();
    $_SESSION=[];
    if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    if($expired)$_SESSION['session_expired']=true;
}
function restore_remembered_session(): bool {
    $cookie=$_COOKIE[auth_cookie_name()] ?? '';
    if($cookie==='')return false;
    if(!is_string($cookie)||!preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D',$cookie,$match)){clear_remember_cookie();return false;}
    $db=db();$db->beginTransaction();
    try {
        $device=query('SELECT * FROM auth_devices WHERE selector_hash=? FOR UPDATE',[hash('sha256',$match[1])])->fetch();
        $user=$device?query('SELECT * FROM users WHERE id=? AND active=1',[$device['user_id']])->fetch():false;
        $hash=hash('sha256',$match[2]);
        $current=$device && hash_equals($device['validator_hash'] ?? '',$hash);
        // Concurrent requests may carry the previous cookie for ten seconds. They
        // do not rotate/set a cookie, so the first response's new cookie wins.
        $previous=$device && !empty($device['previous_validator_hash']) && hash_equals($device['previous_validator_hash'],$hash)
            && strtotime($device['rotated_at'])>=time()-10;
        if(!$device || !$user || strtotime($device['expires_at'])<=time()
            || !hash_equals($device['password_version'],hash('sha256',$user['password_hash'])) || (!$current&&!$previous)) {
            if($device)query('DELETE FROM auth_devices WHERE id=?',[$device['id']]);
            $db->commit();clear_remember_cookie();return false;
        }
        if($current){
            $validator=bin2hex(random_bytes(32));
            query('UPDATE auth_devices SET previous_validator_hash=validator_hash,validator_hash=?,rotated_at=NOW(),last_seen_at=NOW() WHERE id=?',[hash('sha256',$validator),$device['id']]);
        }
        query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$user['id'],'Signed in','Remembered device']);
        $db->commit();
        if($current)set_remember_cookie($match[1].':'.$validator,strtotime($device['expires_at']));
        establish_auth_session($user,(int)$device['id']);return true;
    } catch(Throwable $error) { if($db->inTransaction())$db->rollBack();throw $error; }
}
function auth_foreground_request(): bool {
    // Silent polls and image loads must not keep an unattended session alive.
    $action=$_GET['action'] ?? '';
    if(($_SERVER['HTTP_X_USER_ACTIVITY'] ?? '')==='1')return true;
    return ($_SERVER['REQUEST_METHOD'] ?? '')==='POST'
        || (basename($_SERVER['SCRIPT_NAME'] ?? '')==='portal.php')
        || (is_string($action) && !in_array($action,['','session','message_unread','messages','threads','notifications'],true));
}
function handle_device_action(string $action,array $data,array $user): never {
    if($action==='auth_devices'){
        $items=query('SELECT id,device_label,remembered,created_at,last_seen_at,expires_at FROM auth_devices WHERE user_id=? AND expires_at>NOW() ORDER BY last_seen_at DESC,id DESC',[$user['id']])->fetchAll();
        foreach($items as &$item)$item['current']=(int)$item['id']===(int)($_SESSION['device_id'] ?? 0);unset($item);
        json_out(['items'=>$items]);
    }
    if($action==='revoke_other_devices'){
        query('DELETE FROM auth_devices WHERE user_id=? AND id<>?',[$user['id'],$_SESSION['device_id']]);
        audit('Revoked other sessions');json_out(['ok'=>true]);
    }
    $id=integer_field($data,'id',1);
    if(!query('DELETE FROM auth_devices WHERE id=? AND user_id=?',[$id,$user['id']])->rowCount())fail('Device not found.',404);
    audit('Revoked session');
    $current=$id===(int)$_SESSION['device_id'];
    if($current)end_auth_session();
    json_out(['ok'=>true,'signed_out'=>$current,'csrf'=>csrf()]);
}
