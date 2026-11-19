"""Security and edge-case tests; runs only against integration.py's isolated DB."""
import copy
import hashlib
import json
import time
import urllib.parse


def audit_checks(Client, php, check, admin):
    php("require 'app/bootstrap.php';query('DELETE FROM login_attempts');")
    guest = Client()
    check(guest.call('register', {'name':'Missing Confirm','email':'missing-confirm@test.local','password':'Long-Password-123'})[0] == 422, 'Registration requires server-side password confirmation')
    check(guest.call('register', {'name':'Mismatch','email':'mismatch@test.local','password':'Long-Password-123','confirm_password':'Wrong-Password-123'})[0] == 422, 'Registration rejects mismatched passwords')
    check(guest.call('register', {'name':'Too Long','email':'too-long@test.local','password':'a'*73,'confirm_password':'a'*73})[0] == 422, 'New passwords cannot be silently truncated by bcrypt')
    check(guest.call('register', {'name':'Array Password','email':'array@test.local','password':['a'],'confirm_password':'a'})[0] == 422, 'Malformed password types fail safely')
    password = '  Spaced-Password-123  '
    account = guest.ok('register', {'name':'Remember Test','email':'remember@test.local','password':password,'remember_me':1})['user']
    uid = account['id']
    cookie = copy.copy(next(c for c in guest.jar if c.name == 'ncpcs_remember'))
    check(cookie.expires > time.time()+29*86400 and cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite') == 'Lax', 'Remember cookie persists 30 days with HttpOnly and SameSite')
    device = guest.ok('auth_devices')['items'][0]
    check(device['current'] and device['remembered'] == 1, 'Current remembered device appears in account controls')
    check(not any('hash' in key or 'selector' in key for key in device), 'Device API never exposes token hashes or secrets')
    stored = json.loads(php("require 'app/bootstrap.php';echo json_encode(query('SELECT selector_hash,validator_hash FROM auth_devices WHERE id=?',[(int)stream_get_contents(STDIN)])->fetch());",str(device['id'])))
    selector,validator = urllib.parse.unquote(cookie.value).split(':')
    check(stored['selector_hash'] == hashlib.sha256(selector.encode()).hexdigest() and stored['validator_hash'] == hashlib.sha256(validator.encode()).hexdigest(), 'Database stores only hashes of persistent cookie secrets')
    restarted = Client(); restarted.jar.set_cookie(copy.copy(cookie))
    check(restarted.ok('session')['user']['id'] == uid, 'Remembered login restores a fresh browser session')
    rotated = copy.copy(next(c for c in restarted.jar if c.name == 'ncpcs_remember'))
    check(rotated.value != cookie.value, 'Persistent token rotates on restoration')
    concurrent = Client(); concurrent.jar.set_cookie(copy.copy(cookie))
    check(concurrent.ok('session')['user']['id'] == uid, 'Concurrent old-cookie request survives bounded rotation grace')
    php("require 'app/bootstrap.php';query('UPDATE auth_devices SET rotated_at=DATE_SUB(NOW(),INTERVAL 20 SECOND) WHERE id=?',[(int)stream_get_contents(STDIN)]);",str(device['id']))
    replay = Client(); replay.jar.set_cookie(copy.copy(cookie))
    check(replay.ok('session')['user'] is None, 'Replayed old token fails after rotation grace')
    check(restarted.ok('session')['user'] is None, 'Detected token replay revokes the associated signed-in session')
    fresh = Client(); fresh.ok('login', {'email':'remember@test.local','password':password,'remember_me':1})
    check(fresh.ok('session')['user']['id'] == uid, 'Passwords with leading and trailing spaces work unchanged')
    check(Client().call('login', {'email':'remember@test.local','password':password.strip()})[0] == 401, 'Trimming an intentionally spaced new password does not authenticate')
    token = copy.copy(next(c for c in fresh.jar if c.name == 'ncpcs_remember'))
    fresh.ok('logout', {})
    after = Client();after.jar.set_cookie(token)
    check(after.ok('session')['user'] is None, 'Logout invalidates the persistent login token')
    first = Client();first.login('remember@test.local',password)
    second = Client();second.login('remember@test.local',password)
    second_device = next(x for x in second.ok('auth_devices')['items'] if x['current'])
    check(admin.call('revoke_device', {'id':second_device['id']})[0] == 404, 'Device revocation enforces ownership even for an administrator')
    check(first.request('api.php?action=revoke_other_devices', b'{}', {'Content-Type':'application/json'})[0] == 419, 'Session revocation requires CSRF')
    first.ok('revoke_other_devices', {})
    check(second.ok('session')['user'] is None and first.ok('session')['user']['id'] == uid, 'Sign out other devices preserves only the requesting session')
    third = Client();third.ok('login',{'email':'remember@test.local','password':password,'remember_me':1})
    saved = copy.copy(next(c for c in third.jar if c.name == 'ncpcs_remember'))
    first.ok('profile', {'name':'Remember Test','current_password':password,'new_password':'Changed-Password-123'})
    check(third.ok('session')['user'] is None, 'Password change revokes other device sessions')
    reused=Client();reused.jar.set_cookie(saved)
    check(reused.ok('session')['user'] is None, 'Password change revokes remembered browser tokens')
    check(first.ok('session')['user']['id'] == uid, 'Password change keeps the current browser signed in')
    php("require 'app/bootstrap.php';query('DELETE FROM login_attempts');")
    inactive=Client();inactive.ok('login',{'email':'remember@test.local','password':'Changed-Password-123','remember_me':1})
    data={'id':uid,'name':'Remember Test','email':'remember@test.local','role':'customer','active':0}
    admin.ok('user_save',data);admin.ok('user_save',{**data,'active':1})
    check(inactive.ok('session')['user'] is None, 'Deactivation followed by reactivation cannot revive old sessions')
    expired=Client();expired.ok('login',{'email':'remember@test.local','password':'Changed-Password-123','remember_me':1})
    expcookie=copy.copy(next(c for c in expired.jar if c.name=='ncpcs_remember'))
    did=next(x['id'] for x in expired.ok('auth_devices')['items'] if x['current'])
    php("require 'app/bootstrap.php';query('UPDATE auth_devices SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?',[(int)stream_get_contents(STDIN)]);",str(did))
    expiredrestart=Client();expiredrestart.jar.set_cookie(expcookie)
    check(expiredrestart.ok('session')['user'] is None, 'Expired persistent tokens are rejected and removed')
    idle=Client();idle.login('remember@test.local','Changed-Password-123')
    sid=next(c.value for c in idle.jar if c.name=='ncpcs_session')
    php("$sid=trim(stream_get_contents(STDIN));if(!preg_match('/^[a-zA-Z0-9,-]+$/D',$sid))exit(1);session_save_path(__DIR__.'/storage/sessions');session_name('ncpcs_session');session_id($sid);session_start();$_SESSION['last_activity']=time()-1801;session_write_close();",sid)
    check(idle.call('services')[0] == 401, 'Server enforces 30-minute inactivity timeout')
    check(idle.ok('session')['expired'], 'Expired sessions have an explicit recovery message')
    absolute=Client();absolute.login('remember@test.local','Changed-Password-123')
    sid=next(c.value for c in absolute.jar if c.name=='ncpcs_session')
    php("$sid=trim(stream_get_contents(STDIN));if(!preg_match('/^[a-zA-Z0-9,-]+$/D',$sid))exit(1);session_save_path(__DIR__.'/storage/sessions');session_name('ncpcs_session');session_id($sid);session_start();$_SESSION['auth_started']=time()-43201;session_write_close();",sid)
    check(absolute.call('services')[0] == 401, 'Server enforces 12-hour absolute session limit')
    check(Client().call('auth_devices')[0] == 401, 'Device list requires authentication')
    # Leave credential rate-limit fixtures clean for optional browser workflows.
    php("require 'app/bootstrap.php';query('DELETE FROM login_attempts');")

    # All fixture mutations below stay in the isolated database.
    product = admin.ok('product_save', {'sku':'AUDIT-RESTORE','name':'Audit restore','category':'Audit','price':15,'reorder_level':0})['id']
    admin.ok('product_archive',{'id':product})
    check(any(x['id']==product for x in admin.ok('products',archived=1)['items']), 'Archived products can be listed by authorized staff')
    check(Client().call('product_restore',{'id':product})[0] == 401, 'Product restoration requires authentication')
    owner=Client();owner.login('remember@test.local','Changed-Password-123')
    check(owner.call('product_restore',{'id':product})[0] == 403, 'Customers cannot restore inventory')
    admin.ok('product_restore',{'id':product})
    check(any(x['id']==product and float(x['price'])==15 for x in admin.ok('products')['items']), 'Restoring a product preserves its data')
    check(admin.call('product_restore',{'id':product})[0] == 404, 'Restoring an already-active product fails safely')
    php("require 'app/bootstrap.php';query('UPDATE products SET stock=2147483647 WHERE id=?',[(int)stream_get_contents(STDIN)]);",str(product))
    check(admin.call('stock_move',{'product_id':product,'quantity':1,'reason':'Overflow test'})[0] == 422, 'Stock overflow is rejected without a database error')
    php("require 'app/bootstrap.php';query('UPDATE products SET stock=0 WHERE id=?',[(int)stream_get_contents(STDIN)]);",str(product))
    admin.ok('product_archive',{'id':product})
    a=owner.ok('service_create',{'type':'Repair','title':'Range January','description':'Date fixture','address':'Test address'})['id']
    b=owner.ok('service_create',{'type':'Repair','title':'Range February','description':'Date fixture','address':'Test address'})['id']
    php("require 'app/bootstrap.php';$ids=json_decode(stream_get_contents(STDIN),true);query('UPDATE services SET created_at=?,scheduled_at=? WHERE id=?',['2020-01-31 23:59:59','2020-01-31 23:59:59',$ids[0]]);query('UPDATE services SET created_at=?,scheduled_at=? WHERE id=?',['2020-02-01 00:00:00','2020-02-01 00:00:00',$ids[1]]);",json.dumps([a,b]))
    for kind in ['services','schedule']:
        query=f'report.php?type={kind}&from=2020-01-01&to=2020-01-31'
        code,csv,_=admin.request(query+'&format=csv')
        check(code==200 and b'Range January' in csv and b'Range February' not in csv, kind+' CSV applies inclusive final-date filtering')
        code,html,_=admin.request(query)
        check(code==200 and b'Range January' in html and b'Range February' not in html, kind+' print report matches filtered CSV')
    check(admin.request('report.php?type=services&from=2020-02-30')[0] == 422, 'Invalid report dates rejected')
    check(admin.request('report.php?type=services&from=2020-02-01&to=2020-01-01')[0] == 422, 'Reversed report range rejected')
    check(owner.request('report.php?type=services&from=2020-01-01')[0] == 403, 'Date-filtered exports still enforce role permissions')
    sid=next(c.value for c in owner.jar if c.name=='ncpcs_session')
    php("$sid=trim(stream_get_contents(STDIN));session_save_path(__DIR__.'/storage/sessions');session_name('ncpcs_session');session_id($sid);session_start();$_SESSION['last_activity']=time()-100;session_write_close();",sid)
    owner.ok('message_unread')
    age=int(php("$sid=trim(stream_get_contents(STDIN));session_save_path(__DIR__.'/storage/sessions');session_name('ncpcs_session');session_id($sid);session_start();echo time()-$_SESSION['last_activity'];session_write_close();",sid))
    check(age>=100, 'Background message polling does not extend inactivity timeout')
