<?php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Auth/google.php';
function check_google(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function rejects_google(callable $call,string $label): void {
    try { $call(); } catch (AppError $e) { check_google(true,$label); return; }
    throw new RuntimeException($label);
}
$config['google_client_id']='test-client';
$config['google_client_secret']='test-secret';
$config['app_url']='http://localhost/SAD/';
check_google(google_redirect_uri()==='http://localhost/SAD/google-auth.php','Canonical subdirectory callback');
foreach (['http://example.com','https://site.test/?x=1','https://user:pass@site.test','https://site.test/#x',''] as $url) {
    $config['app_url']=$url;
    check_google(!google_auth_available(),'Reject unsafe or incomplete base URL');
}
$config['app_url']='https://example.com/SAD';
check_google(google_auth_available(),'Configured HTTPS login available');
$config['google_client_secret']='';
check_google(!google_auth_available(),'Missing secret disables Google');
$config['google_client_secret']='test-secret';
$claims=['iss'=>'https://accounts.google.com','aud'=>'test-client','sub'=>'google-subject-1','email'=>'PERSON@example.com','email_verified'=>true,'nonce'=>'expected','iat'=>time(),'exp'=>time()+300,'name'=>'Test Person'];
function response_for(array $claims): array { return ['id_token'=>'test.'.rtrim(strtr(base64_encode(json_encode($claims)),'+/','-_'),'=').'.test']; }
check_google(google_identity_from_token_response(response_for($claims),'expected')['email']==='person@example.com','Validate trusted token response');
foreach (['iss'=>'https://attacker.test','aud'=>'other-client','azp'=>'other-client','exp'=>time()-1,'iat'=>time()+3600,'nonce'=>'wrong','sub'=>'','email_verified'=>false,'email'=>'invalid'] as $key=>$value) {
    $bad=array_replace($claims,[$key=>$value]);
    rejects_google(fn()=>google_identity_from_token_response(response_for($bad),'expected'),'Reject invalid claim '.$key);
}
foreach ([[],['id_token'=>'bad'],['id_token'=>[]],['id_token'=>'x.e30.x']] as $bad) rejects_google(fn()=>google_identity_from_token_response($bad,'expected'),'Reject malformed response');
$_SESSION=['google_auth'=>['state'=>'correct','created'=>time(),'nonce'=>'test']];
check_google(google_consume_state('correct')['nonce']==='test','Consume matching session state');
rejects_google(fn()=>google_consume_state('correct'),'Reject replayed state');
foreach ([['wrong',time()],['correct',time()-601],[['correct'],time()]] as [$state,$created]) {
    $_SESSION['google_auth']=['state'=>'correct','created'=>$created];
    rejects_google(fn()=>google_consume_state($state),'Reject wrong, expired or malformed state');
    check_google(!isset($_SESSION['google_auth']),'Invalid attempt consumed');
}
echo "Google unit checks passed.\n";
