<?php
declare(strict_types=1);

function google_redirect_uri(): string {
    global $config;
    $base=rtrim(trim((string)($config['app_url'] ?? '')),'/');
    $url=parse_url($base);
    if (!filter_var($base,FILTER_VALIDATE_URL) || !$url
        || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])
        || !(($url['scheme'] ?? '')==='https' || (($url['scheme'] ?? '')==='http' && in_array($url['host'] ?? '',['localhost','127.0.0.1','[::1]'],true)))) return '';
    return $base.'/google-auth.php';
}

function google_auth_available(): bool {
    global $config;
    return trim((string)($config['google_client_id'] ?? ''))!==''
        && trim((string)($config['google_client_secret'] ?? ''))!==''
        && google_redirect_uri()!=='' && function_exists('curl_init');
}

function google_auth_start(array $data=[]): string {
    global $config;
    if (!google_auth_available()) fail('Google sign-in is not available yet. Please use email and password.',503);
    if (current_user()) fail('Sign out before continuing with another account.',409);
    $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '').'|google');
    if (query('SELECT COUNT(*) FROM login_attempts WHERE fingerprint=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$fingerprint])->fetchColumn() >= 15) fail('Too many attempts. Try again in 15 minutes.',429);
    query('INSERT INTO login_attempts(fingerprint) VALUES(?)',[$fingerprint]);
    query('DELETE FROM login_attempts WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
    $pending=[
        'remember'=>integer_field($data,'remember_me',0,1)===1,
        'state'=>bin2hex(random_bytes(32)),
        'nonce'=>bin2hex(random_bytes(32)),
        'verifier'=>bin2hex(random_bytes(32)),
        'created'=>time(),
        'redirect_uri'=>google_redirect_uri(),
    ];
    $_SESSION['google_auth']=$pending;
    return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
        'client_id'=>$config['google_client_id'], 'redirect_uri'=>$pending['redirect_uri'],
        'response_type'=>'code', 'scope'=>'openid email profile', 'prompt'=>'select_account',
        'state'=>$pending['state'], 'nonce'=>$pending['nonce'],
        'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$pending['verifier'],true)),'+/','-_'),'='),
        'code_challenge_method'=>'S256',
    ],'','&',PHP_QUERY_RFC3986);
}

function google_consume_state(mixed $state): array {
    $pending=$_SESSION['google_auth'] ?? null;
    unset($_SESSION['google_auth']); // Every callback consumes its attempt, including cancellation.
    if (!is_array($pending) || !is_string($state) || !hash_equals($pending['state'],$state)
        || $pending['created']<time()-600 || $pending['created']>time()) {
        fail('Your Google sign-in expired or could not be verified. Please try again.');
    }
    return $pending;
}

/** Only decode tokens returned directly by Google's token endpoint over verified TLS.
 * Never call this with a browser-supplied token. Google documents this server flow:
 * https://developers.google.com/identity/openid-connect/openid-connect#obtainuserinfo
 */
function google_identity_from_token_response(array $response, string $nonce): array {
    global $config;
    $jwt=$response['id_token'] ?? null;
    if (!is_string($jwt) || strlen($jwt)>20000) fail('Google did not return a valid identity. Please try again.');
    $parts=explode('.',$jwt);
    if (count($parts)!==3 || !preg_match('/^[A-Za-z0-9_-]+$/D',$parts[1])) fail('Google did not return a valid identity. Please try again.');
    $payload=base64_decode(strtr($parts[1],'-_','+/'),true);
    $claims=$payload===false?null:json_decode($payload,true);
    if (!is_array($claims)
        || !in_array($claims['iss'] ?? '',['https://accounts.google.com','accounts.google.com'],true)
        || ($claims['aud'] ?? null)!==$config['google_client_id']
        || (isset($claims['azp']) && $claims['azp']!==$config['google_client_id'])
        || !is_int($claims['exp'] ?? null) || $claims['exp']<=time()
        || !is_int($claims['iat'] ?? null) || $claims['iat']>time()+60
        || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce,$claims['nonce'])
        || !is_string($claims['sub'] ?? null) || !preg_match('/^[\x21-\x7e]{1,255}$/D',$claims['sub'])
        || !in_array($claims['email_verified'] ?? false,[true,'true'],true)
        || !is_string($claims['email'] ?? null) || strlen($claims['email'])>190
        || !filter_var($claims['email'],FILTER_VALIDATE_EMAIL)) {
        fail('Google could not verify your account and email address. Please try again.');
    }
    $name=is_string($claims['name'] ?? null)?trim($claims['name']):'';
    return ['subject'=>$claims['sub'],'email'=>strtolower($claims['email']),
        'name'=>mb_substr($name!==''?$name:explode('@',$claims['email'])[0],0,120)];
}

function google_exchange_code(string $code, array $pending): array {
    global $config;
    if (!google_auth_available() || google_redirect_uri()!==$pending['redirect_uri']) fail('Google sign-in configuration changed. Please try again.',503);
    // Fixed HTTPS endpoint, certificate verification and no redirects protect the secret.
    $curl=curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($curl,[
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>25,
        CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS=>http_build_query([
            'code'=>$code, 'client_id'=>$config['google_client_id'], 'client_secret'=>$config['google_client_secret'],
            'redirect_uri'=>$pending['redirect_uri'], 'grant_type'=>'authorization_code', 'code_verifier'=>$pending['verifier'],
        ],'','&',PHP_QUERY_RFC3986),
    ]);
    $body=curl_exec($curl);
    $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body===false || $status!==200) fail('Unable to complete Google sign-in. Please try again or use email and password.',502);
    $response=json_decode($body,true);
    if (!is_array($response)) fail('Google returned an unexpected response. Please try again.',502);
    return google_identity_from_token_response($response,$pending['nonce']);
}

function google_find_or_create_user(array $identity): array {
    $db=db();
    $db->beginTransaction();
    try {
        $user=query('SELECT u.* FROM users u JOIN google_accounts g ON g.user_id=u.id WHERE g.google_subject=? FOR UPDATE',[$identity['subject']])->fetch();
        if ($user) {
            if (!(int)$user['active']) fail('This account is inactive. Please contact the NCPCS office.',403);
        } else {
            // Matching an email alone must never grant access to an existing account.
            if (query('SELECT id FROM users WHERE email=?',[$identity['email']])->fetchColumn()) {
                fail('An account already uses this email. Sign in with your password or use Forgot password.',409);
            }
            $hash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
            query("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'customer')",[$identity['name'],$identity['email'],$hash]);
            $id=(int)$db->lastInsertId();
            query('INSERT INTO google_accounts(user_id,google_subject) VALUES(?,?)',[$id,$identity['subject']]);
            query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$id,'Registered account','Using Google']);
            $user=query('SELECT * FROM users WHERE id=?',[$id])->fetch();
        }
        $db->commit();
        return $user;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof PDOException && $e->getCode()==='23000') fail('This account was updated during sign-in. Please try again or sign in with your password.',409);
        throw $e;
    }
}
