<?php
// Invoked only by integration.py with its isolated test database.
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Auth/google.php';
if (!preg_match('/^ncpcs_test_[a-f0-9]{10}$/D',$config['db_name'])) throw new RuntimeException('Test database required');
function google_db_check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
$identity=['subject'=>'test-google-user','email'=>'google-customer@test.local','name'=>'Google Customer','role'=>'admin'];
$user=google_find_or_create_user($identity);
google_db_check($user['role']==='customer','Google signup cannot elevate role');
google_db_check(password_get_info($user['password_hash'])['algoName']!=='unknown','Google signup stores an unguessable local password hash');
$repeat=google_find_or_create_user($identity);
google_db_check($repeat['id']===$user['id'],'Repeated Google login reuses account');
$changed=google_find_or_create_user(array_replace($identity,['email'=>'changed@test.local']));
google_db_check($changed['id']===$user['id'] && $changed['email']===$user['email'],'Google subject identifies account even if Google email changes');
foreach (['admin@ncpcs.local','google-customer@test.local'] as $email) {
    try { google_find_or_create_user(['subject'=>'different-google-user','email'=>$email,'name'=>'Other']); throw new RuntimeException('Account takeover allowed'); }
    catch (AppError $e) { google_db_check($e->getCode()===409,'Email collision never links an existing account'); }
}
query('UPDATE users SET active=0 WHERE id=?',[$user['id']]);
try { google_find_or_create_user($identity); throw new RuntimeException('Inactive account signed in'); }
catch (AppError $e) { google_db_check($e->getCode()===403,'Inactive Google account rejected'); }
google_db_check(!db()->inTransaction(),'Failed Google login rolls back transaction');
google_db_check((int)query('SELECT COUNT(*) FROM google_accounts WHERE google_subject=?',[$identity['subject']])->fetchColumn()===1,'One Google identity remains after retries and rejection');
google_db_check((int)query("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action='Registered account'",[$user['id']])->fetchColumn()===1,'Google registration audited exactly once');
