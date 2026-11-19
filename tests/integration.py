"""Isolated HTTP + optional Chrome checks. Requires XAMPP MySQL on localhost.
Run: python tests/integration.py [--browser]
Creates and removes only its own randomly named ncpcs_test_* database.
"""
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import struct
import subprocess
import sys
import time
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import zlib

sys.stdout.reconfigure(encoding='utf-8')

ROOT = Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
DB = 'ncpcs_test_' + secrets.token_hex(5)
ENV = {**os.environ, 'DB_NAME': DB, 'OLLAMA_ENABLED': '0', 'OLLAMA_API_KEY': '', 'AI_DEFAULT_PROVIDER': 'auto', 'OPENAI_API_KEY': '', 'GEMINI_API_KEY': '', 'META_API_KEY': '', 'META_API_KEYS': '', 'GOOGLE_CLIENT_ID': 'test-client.apps.googleusercontent.com', 'GOOGLE_CLIENT_SECRET': 'test-secret'}
SERVER = None
MAILBOX = None
UPLOAD_TEMP = None
PORT = 0
PASSWORD = ''
BLUEPRINT_ID = 0
checks = 0

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
    print('PASS', label, flush=True)

def php(code, stdin=''):
    return subprocess.check_output([PHP, '-r', code], cwd=ROOT, env=ENV, input=stdin.encode(), stderr=subprocess.STDOUT).decode()

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.token = self.call('session')[1]['csrf']
    def request(self, path, body=None, headers=None):
        request = urllib.request.Request(f'http://127.0.0.1:{PORT}/'+path, data=body, headers=headers or {})
        try:
            response = self.http.open(request)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read()
        return response.status, raw, response.headers
    def call(self, action, data=None, **params):
        if action in ('stock_move','service_create','quotation_save') and data is not None:
            data = {'request_key': secrets.token_hex(16), **data}
        headers = {}
        body = None
        if data is not None:
            body = json.dumps(data).encode()
            headers = {'Content-Type': 'application/json', 'X-CSRF-Token': self.token}
        status, raw, _ = self.request('api.php?'+urllib.parse.urlencode({'action': action, **params}),body,headers)
        try:
            result = json.loads(raw)
        except Exception:
            raise AssertionError(f'{action} returned {status}: {raw[:300]!r}')
        if 'csrf' in result:
            self.token = result['csrf']
        return status, result
    def ok(self, action, data=None, **params):
        if action == 'register' and data is not None:
            data = {'confirm_password': data.get('password'), **data}
        if action == 'profile' and data and data.get('new_password'):
            data = {'confirm_new_password': data['new_password'], **data}
        status, result = self.call(action, data, **params)
        assert status == 200, (action, status, result)
        return result
    def login(self, email, password):
        return self.ok('login', {'email': email, 'password': password})
    def upload_product(self, data, content, filename='product.webp', mime='image/webp'):
        boundary='----Product'+secrets.token_hex(8)
        parts=[]
        for name,value in data.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode())
        status,raw,_=self.request('api.php?action=product_save',b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':self.token})
        return status,json.loads(raw)


def browser_checks():
    sys.path.insert(0, str(ROOT/'.tools'))
    from playwright.sync_api import sync_playwright
    artifacts = ROOT/'tests'/'.artifacts'
    artifacts.mkdir(exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
        context = browser.new_context(viewport={'width':1440,'height':1000})
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(f'http://127.0.0.1:{PORT}/')
        page.get_by_role('heading',name='A clearer path to a safer home.').wait_for()
        check(page.locator('.landing-feature-card').count()==4,'Public landing page presents four features')
        theme_landing_checks(page, context, PORT, check, artifacts)
        page.screenshot(path=str(artifacts/'landing-desktop.png'),full_page=True,animations='disabled')
        page.set_viewport_size({'width':390,'height':844})
        page.screenshot(path=str(artifacts/'landing-mobile.png'),full_page=True,animations='disabled')
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'Landing page has no mobile horizontal overflow')
        page.locator('#landing-menu-button').click()
        check(page.locator('#landing-links').is_visible(),'Landing mobile menu opens')
        bookmark_page=context.new_page()
        bookmark_page.goto(f'http://127.0.0.1:{PORT}/#planner/42')
        bookmark_page.wait_for_function("location.pathname.endsWith('/portal.php') && location.hash === '#planner/42'")
        check(True,'Old portal bookmarks redirect from landing page')
        bookmark_page.close()
        page.set_viewport_size({'width':1440,'height':1000})
        page.locator('.landing-hero-actions a[href="portal.php?register=1"]').click()
        page.get_by_role('button',name='Create account',exact=True).wait_for()
        check(not page.locator('#register-fields').is_hidden(),'Landing signup opens customer registration')
        google_browser_checks(page, PORT, check)
        for width,height in [(1366,768),(1440,600),(390,844),(390,667)]:
            page.set_viewport_size({'width':width,'height':height})
            for mode in ['login','register']:
                page.evaluate("mode => setAuthMode(mode)",mode)
                check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'{mode} fits width at {width}x{height}')
                page.locator('#auth-submit').scroll_into_view_if_needed()
                check(page.locator('#auth-submit').is_visible(),f'{mode} submit remains reachable at {width}x{height}')
        page.evaluate("setAuthMode('login')")
        page.set_viewport_size({'width':1440,'height':1000})
        recovery_browser_checks(page, PORT, check, MAILBOX)
        page.goto(f'http://127.0.0.1:{PORT}/portal.php')
        page.locator('#login-email').fill('admin@ncpcs.local')
        page.locator('#login-password').fill(PASSWORD)
        page.locator('#auth-submit').click()
        try:
            page.locator('.chart-panel').wait_for(timeout=30000)
        except Exception:
            print('BROWSER ERRORS:',errors,flush=True)
            print('BROWSER PAGE:',page.locator('body').inner_text(),flush=True)
            page.screenshot(path=str(artifacts/'failure.png'),full_page=True)
            raise
        page.screenshot(path=str(artifacts/'desktop-dashboard.png'),full_page=True,animations='disabled')
        sidebar_reachable = """() => {
            const sidebar = document.querySelector('#sidebar');
            const button = document.querySelector('#logout').getBoundingClientRect();
            return button.top >= 0 && button.bottom <= window.innerHeight;
        }"""
        page.set_viewport_size({'width':1440,'height':600})
        page.locator('#logout').scroll_into_view_if_needed()
        check(page.evaluate(sidebar_reachable),'Desktop Sign out stays visible')
        page.set_viewport_size({'width':1366,'height':768})
        for nav_link in page.locator('#navigation a').all():
            nav_link.scroll_into_view_if_needed()
            check(nav_link.is_visible(), 'Administrator navigation remains reachable at laptop size')
        page.set_viewport_size({'width':1440,'height':1000})
        for route, heading in [('inventory','Inventory'),('customers','Customers'),('services','Service requests'),('schedule','Technician schedule'),('messages','Messages'),('planner','CCTV planner'),('reports','Reports'),('users','Team & accounts'),('settings','Settings'),('user-logs','User logs'),('audit-logs','Audit logs')]:
            page.locator(f'a[data-page="{route}"]').click()
            page.get_by_role('heading',name=heading,exact=True).wait_for()
            check(not page.locator('#content > .alert-danger').count(),f'Browser renders {route}')
        for log_route in ['user-logs','audit-logs']:
            page.goto(f'http://127.0.0.1:{PORT}/portal.php#{log_route}')
            page.locator('.log-user-card').first.wait_for()
            page.locator('#log-search input').fill('customer@test.local')
            page.locator('#log-search button').click()
            page.wait_for_function("() => document.querySelectorAll('.log-user-card').length === 1")
            check('Test Customer' in page.locator('.log-user-card').inner_text(),log_route+' finds a user by email')
            page.locator('.log-user-card').click()
            page.locator('.log-account-heading').wait_for()
            check('Test Customer' in page.locator('.log-account-heading').inner_text(),log_route+' opens selected account')
            page.locator('#logs-next').click()
            page.wait_for_function("() => document.querySelector('#log-results').textContent.includes('Page 2 of')")
            page.reload()
            page.locator('.log-account-heading').wait_for()
            check('Test Customer' in page.locator('.log-account-heading').inner_text(),log_route+' preserves account on reload')
            page.set_viewport_size({'width':390,'height':844})
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),log_route+' detail fits mobile')
            page.get_by_role('link',name='All users',exact=True).click()
            page.locator('.log-user-card').first.wait_for()
            check(page.locator('.log-user-card').count()>1,log_route+' returns to all user groups')
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),log_route+' user groups fit mobile')
            page.set_viewport_size({'width':1440,'height':1000})
        page.locator('a[data-page="inventory"]').click()
        page.locator('.product-card').first.wait_for()
        page.evaluate("() => { for (const i of document.querySelectorAll('.product-card img')) i.loading='eager'; }")
        page.evaluate("() => Promise.all([...document.querySelectorAll('.product-card img')].map(i=>i.decode()))")
        page.screenshot(path=str(artifacts/'inventory-desktop.png'),full_page=True,animations='disabled')
        check(page.locator('.product-card img').count()==8,'Catalog cards display all eight sample pictures')
        page.set_viewport_size({'width':390,'height':844})
        page.screenshot(path=str(artifacts/'inventory-mobile.png'),full_page=True,animations='disabled')
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'Mobile photo catalog has no horizontal overflow')
        page.set_viewport_size({'width':1440,'height':1000})
        page.get_by_role('button',name='Add product',exact=True).click()
        page.locator('#f-name').fill('Browser test product')
        page.locator('#f-sku').fill('BROWSER-TEST')
        page.locator('#f-category').fill('Accessories')
        page.locator('#f-price').fill('123.45')
        page.locator('#f-image').set_input_files(str(ROOT/'assets/images/products/outdoor-camera.webp'))
        page.locator('#product-image-preview img').wait_for()
        page.screenshot(path=str(artifacts/'product-photo-editor.png'),animations='disabled')
        check(page.locator('#product-image-preview img').get_attribute('src').startswith('blob:'),'Product form previews selected picture before saving')
        page.locator('#editor-form button[type=submit]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.get_by_text('Browser test product',exact=True).wait_for()
        check(True,'Browser product form persists through AJAX')
        page.locator('#table-search').fill('BROWSER-TEST')
        check(page.locator('.product-card:visible').count()==1,'Inventory search filters picture cards')
        card=page.locator('.product-card:visible')
        page.wait_for_function("() => document.querySelector('.product-card:not([hidden]) img')?.naturalWidth>0")
        check(card.locator('img').get_attribute('src').startswith('product-image.php'),'Browser upload persists and renders on product card')
        card.get_by_role('button',name='Edit Browser test product',exact=True).click()
        page.locator('#f-image').set_input_files(str(ROOT/'assets/images/products/indoor-camera.webp'))
        page.locator('#editor-form button[type=submit]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.get_by_text('Browser test product',exact=True).wait_for()
        page.locator('#table-search').fill('BROWSER-TEST')
        page.locator('.product-card:visible').get_by_role('button',name='Edit Browser test product',exact=True).click()
        page.locator('#f-remove-image').check()
        page.locator('#editor-form button[type=submit]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.get_by_text('Browser test product',exact=True).wait_for()
        page.locator('#table-search').fill('BROWSER-TEST')
        check(page.locator('.product-card:visible img').count()==0,'Browser can replace and remove a product picture')
        page.get_by_role('button',name='Table view',exact=True).click()
        check(page.locator('tr[data-search]:visible').count()==1,'Table view preserves the active product search')
        page.locator('#table-search').fill('no-matching-product-999')
        check(page.get_by_text('No matching products',exact=True).is_visible(),'Empty catalog search explains no matches')
        page.locator('#table-search').fill('')
        page.locator('#table-filter').select_option('Outdoor camera')
        check(page.locator('tr[data-search]:visible').count()==1,'Category filtering works in table view')
        page.locator('a[data-page="services"]').click()
        page.get_by_role('button',name='New service request',exact=True).click()
        page.locator('#f-title').fill('Browser service request')
        page.locator('#f-description').fill('Install CCTV at the front gate.')
        page.locator('#f-address').fill('Nasugbu test address')
        page.locator('#editor-form button[type=submit]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.get_by_text('Browser service request',exact=True).wait_for()
        check(True,'Browser service request persists through AJAX')
        page.locator('a[data-page="planner"]').click()
        page.get_by_role('heading',name='CCTV planner',exact=True).wait_for()
        page.locator(f'a[href="#planner/{BLUEPRINT_ID}"]').click()
        page.get_by_role('heading',name='Suggested camera placements',exact=True).wait_for()
        check(page.locator('.camera-marker').count()==2,'Saved blueprint renders both camera markers')
        check(page.locator('.estimate-row.total').inner_text().find('20,845.00')>=0,'Saved estimate displays equipment and labor total')
        page.get_by_role('button',name='Record office review').click()
        page.locator('#f-review_notes').fill('Test fixture reviewed; site measurements still needed.')
        page.locator('#editor-form button[type=submit]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.get_by_text('Reviewed by the office on',exact=False).wait_for()
        check(True,'Office blueprint review persists through AJAX')
        page.screenshot(path=str(artifacts/'planner.png'),full_page=True,animations='disabled')
        def available_provider_fixture(route):
            response=route.fetch()
            body=response.json()
            body['ai_ready']=True
            body['ai_providers']=['ollama','openai','gemini','meta']
            body['ai_auto_providers']=['ollama']
            body['ai_default_provider']='ollama'
            route.fulfill(response=response,json=body)
        page.route('**/api.php?action=blueprint&id=*',available_provider_fixture)
        page.reload()
        page.get_by_role('heading',name='Suggested camera placements',exact=True).wait_for()
        page.locator('#analyze-plan').click()
        check(page.locator('#f-provider option').count()==5,'Blueprint form offers automatic and all four providers')
        check(page.locator('#f-provider').input_value()=='ollama','Blueprint form defaults to Ollama Cloud')
        check('sent online to Ollama Cloud' in page.locator('#ai-processing-note').inner_text(),'Ollama explains online processing')
        page.locator('#ai-consent').check()
        page.locator('#f-provider').select_option('meta')
        check(page.locator('#f-provider').input_value()=='meta','Meta choice is selectable')
        check(not page.locator('#ai-consent').is_checked(),'Switching providers requires fresh consent')
        check('cloud provider' in page.locator('#ai-processing-note').inner_text(),'Cloud selection explains external processing')
        page.locator('#f-provider').select_option('auto')
        check('sent online to Ollama Cloud' in page.locator('#ai-processing-note').inner_text(),'Automatic mode explains Cloud processing')
        page.locator('#editor-modal [data-bs-dismiss="modal"]').first.click()
        if page.locator('[data-discard-changes]').is_visible():
            page.locator('[data-discard-changes]').click()
        page.locator('#editor-modal').wait_for(state='hidden')
        page.unroute('**/api.php?action=blueprint&id=*',available_provider_fixture)
        customer_context=browser.new_context(viewport={'width':1280,'height':900})
        cp=customer_context.new_page()
        cp.on('pageerror',lambda error:errors.append(str(error)))
        cp.goto(f'http://127.0.0.1:{PORT}/portal.php')
        cp.locator('#login-email').fill('customer@test.local')
        cp.locator('#login-password').fill('Customer-Test-123')
        cp.locator('#auth-submit').click()
        cp.get_by_role('heading',name='Hello, Test.').wait_for()
        cp.locator('a[data-page="messages"]').click()
        cp.locator('#chat-form textarea').fill('Browser message from customer')
        cp.locator('#chat-form button').click()
        cp.get_by_text('Browser message from customer',exact=True).wait_for()
        page.locator('#message-alert [data-unread]').wait_for(state='visible',timeout=10000)
        check(page.locator('#message-alert [data-unread]').inner_text()=='1','Unread badge appears away from Messages')
        page.locator('a[data-page="messages"]').click()
        page.locator('#message-alert [data-unread]').wait_for(state='hidden',timeout=10000)
        check(True,'Unread badge clears after opening conversation')
        page.get_by_text('Browser message from customer',exact=True).last.wait_for()
        page.locator('#chat-form textarea').fill('Browser reply from office')
        page.locator('#chat-form button').click()
        cp.get_by_text('Browser reply from office',exact=True).wait_for(timeout=10000)
        check(True,'Customer and admin browser sessions exchange messages by AJAX')
        customer_context.close()
        page.set_viewport_size({'width':390,'height':844})
        page.goto(f'http://127.0.0.1:{PORT}/portal.php#dashboard')
        page.locator('.chart-panel').wait_for(timeout=30000)
        page.screenshot(path=str(artifacts/'mobile-dashboard.png'),full_page=True,animations='disabled')
        if page.evaluate('document.documentElement.scrollWidth > window.innerWidth'):
            print('OVERFLOW:',page.evaluate("[...document.querySelectorAll('body *')].filter(x=>x.getBoundingClientRect().right>window.innerWidth).slice(0,12).map(x=>[x.tagName,x.className,x.getBoundingClientRect().width])"))
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'Mobile dashboard has no horizontal overflow')
        page.locator('#menu-button').click()
        page.set_viewport_size({'width':390,'height':620})
        page.locator('#logout').scroll_into_view_if_needed()
        check(page.evaluate(sidebar_reachable),'Mobile Sign out stays visible')
        page.set_viewport_size({'width':390,'height':844})
        page.locator('a[data-page="messages"]').click()
        page.get_by_role('heading',name='Messages',exact=True).wait_for()
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'Mobile messaging has no horizontal overflow')
        from advanced_browser import advanced_browser
        advanced_browser(browser, PORT, PASSWORD, BLUEPRINT_ID, check, artifacts)
        workspace_ui_checks(page, PORT, php, check, artifacts)
        check(not errors,'No JavaScript runtime errors: '+str(errors))
        browser.close()

try:
    install = subprocess.check_output([PHP, 'install.php'],cwd=ROOT,env=ENV).decode()
    PASSWORD = re.search(r'Temporary password: (.+)',install).group(1).strip()
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0))
        PORT=sock.getsockname()[1]
    from password_recovery import Mailbox, recovery_checks, recovery_browser_checks
    from google_auth import google_checks, google_browser_checks
    from audit_checks import audit_checks
    from ui_browser import theme_landing_checks, workspace_ui_checks
    MAILBOX = Mailbox()
    UPLOAD_TEMP = tempfile.TemporaryDirectory(prefix='ncpcs_test_upload_')
    ENV.update(APP_URL=f'http://127.0.0.1:{PORT}', MAIL_FROM='noreply@test.local')
    server_log=open(ROOT/'storage'/'test-server.log','w')
    SERVER=subprocess.Popen([PHP,'-d','SMTP=127.0.0.1','-d',f'smtp_port={MAILBOX.port}','-d',f'upload_tmp_dir={UPLOAD_TEMP.name}','-S',f'127.0.0.1:{PORT}','router.php'],cwd=ROOT,env=ENV,stdout=server_log,stderr=server_log,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    for _ in range(40):
        try:
            anonymous=Client()
            break
        except urllib.error.URLError:
            time.sleep(.1)
    check(anonymous.call('products')[0]==401,'Unauthenticated inventory access rejected')
    check(anonymous.request('config.local.php')[0]==404,'Development router blocks configuration')
    check(anonymous.call('logout')[0]==405,'GET cannot trigger mutations')
    check(anonymous.request('api.php?action=login',b'{}',{'Content-Type':'application/json'})[0]==419,'Missing CSRF token rejected')
    admin=Client();admin.login('admin@ncpcs.local',PASSWORD)
    check(admin.ok('session')['user']['role']=='admin','Administrator authentication')
    customer=Client();customer.ok('register',{'name':'Test Customer','email':'customer@test.local','password':'Customer-Test-123'})
    other=Client();other.ok('register',{'name':'Other Customer','email':'other@test.local','password':'Customer-Test-123'})
    cid=customer.ok('session')['user']['id']
    check(customer.call('users')[0]==403,'Customer cannot enumerate accounts')
    check(customer.call('settings_save',{})[0]==403,'Customer cannot alter rates')
    for role in ['technician','staff']:
        admin.ok('user_save',{'id':0,'name':'Test '+role,'email':role+'@test.local','password':'Team-Test-123','role':role,'active':1})
    users=admin.ok('users')['items'];tid=next(u['id'] for u in users if u['role']=='technician')
    technician=Client();technician.login('technician@test.local','Team-Test-123')
    staff=Client();staff.login('staff@test.local','Team-Test-123')
    check(staff.call('user_save',{'id':0,'role':'admin','email':'bad@test.local','name':'Bad','password':'Password123','active':1})[0]==403,'Staff cannot create administrators')
    check(technician.call('threads')[0]==403,'Technician cannot read customer conversations')
    product=admin.ok('product_save',{'id':0,'sku':'TEST-PART','name':'=SUM(1,1)','category':'Computer parts','price':100,'reorder_level':2})['id']
    image_data={'id':product,'sku':'TEST-PART','name':'=SUM(1,1)','category':'Computer parts','price':100,'reorder_level':2}
    picture=(ROOT/'assets/images/products/outdoor-camera.webp').read_bytes()
    replacement=(ROOT/'assets/images/products/indoor-camera.webp').read_bytes()
    check(technician.upload_product(image_data,picture)[0]==403,'Technician cannot change product pictures')
    check(customer.upload_product(image_data,picture)[0]==403,'Customer cannot upload product pictures')
    check(admin.upload_product(image_data,b'<svg xmlns="http://www.w3.org/2000/svg"/>','fake.png','image/png')[0]==422,'Product picture rejects SVG disguised as PNG')
    check(admin.upload_product(image_data,picture+b'0'*(2*1024*1024),'big.webp')[0]==422,'Product picture rejects oversized uploads')
    check(admin.upload_product(image_data,picture)[0]==200,'Product picture saves with product through multipart AJAX')
    image_url=f'product-image.php?id={product}'
    check(admin.request(image_url)[1]==picture,'Uploaded product picture is retrieved intact')
    check(anonymous.request(image_url)[0]==401 and customer.request(image_url)[0]==403,'Product pictures enforce account permissions')
    check(technician.request(image_url)[0]==200,'Technician can see product pictures for service parts')
    metadata=next(x for x in admin.ok('products')['items'] if x['id']==product)
    check(metadata['has_image'] and metadata['image_url']==image_url,'Catalog returns the saved picture URL')
    admin.ok('product_save',image_data)
    check(admin.request(image_url)[1]==picture,'Editing details preserves the existing picture')
    check(admin.upload_product({**image_data,'sku':'CAM-OUT-4MP'},replacement)[0]==409,'Duplicate SKU rejects product and picture changes together')
    check(admin.request(image_url)[1]==picture,'Failed save preserves the previous picture')
    check(staff.upload_product(image_data,replacement)[0]==200 and admin.request(image_url)[1]==replacement,'Staff can replace a product picture')
    admin.ok('product_save',{**image_data,'remove_image':1})
    check(admin.request(image_url)[0]==404,'Removing a picture removes its image endpoint')
    seeded=next(x for x in admin.ok('products')['items'] if x['sku']=='CAM-OUT-4MP')
    check(seeded['image_is_sample'] and admin.request(seeded['image_url'])[0]==200,'Seeded products have local illustrative pictures')
    subprocess.check_output([PHP,'migrate.php'],cwd=ROOT,env=ENV)
    check(len(admin.ok('products')['items'])==9,'Rerunning additive migration preserves products')
    admin.ok('stock_move',{'product_id':product,'quantity':5,'reason':'Test opening stock'})
    check(admin.call('stock_move',{'product_id':product,'quantity':-6,'reason':'Overdraw'})[0]==422,'Overselling is rejected')
    check(next(p['stock'] for p in admin.ok('products')['items'] if p['id']==product)==5,'Rejected deduction leaves stock unchanged')
    check(admin.call('stock_move',{'product_id':product,'quantity':1.5,'reason':'Fraction'})[0]==422,'Fractional stock is rejected')
    check(admin.call('product_archive',{'id':product})[0]==422,'Cannot archive stocked products')
    service=customer.ok('service_create',{'type':'Installation','title':'Test camera installation','description':'Front gate and garage','address':'Nasugbu'})['id']
    check(other.call('service',id=service)[0]==404,'Other customer cannot read service details')
    check(technician.call('service',id=service)[0]==404,'Technician cannot read an unassigned service')
    update={'id':service,'status':'Scheduled','technician_id':tid,'scheduled_at':'2026-11-20T09:00','duration_minutes':120,'notes':'Install and verify','warranty_until':'2027-11-20'}
    admin.ok('service_update',update)
    second=other.ok('service_create',{'type':'Repair','title':'Other repair','description':'Repair camera','address':'Batangas'})['id']
    check(admin.call('service_update',{**update,'id':second,'scheduled_at':'2026-11-20T10:00'})[0]==422,'Overlapping technician appointments rejected')
    admin.ok('service_update',{**update,'id':second,'scheduled_at':'2026-11-20T11:00'})
    check(True,'Back-to-back appointments accepted')
    technician.ok('stock_move',{'product_id':product,'quantity':-2,'reason':'Installed parts','service_id':service})
    check(next(p['stock'] for p in admin.ok('products')['items'] if p['id']==product)==3,'Service parts deduct stock')
    check(len(customer.ok('service',id=service)['parts'])==1,'Customer sees parts in own service history')
    technician.ok('service_update',{'id':service,'status':'In Progress','notes':'Starting work'})
    technician.ok('job_save',{'service_id':service,'revision':0,'notes':'Starting checklist','checks':[]})
    job=technician.ok('job_card',service_id=service)
    technician.ok('job_save',{'service_id':service,'revision':job['card']['revision'],'notes':'Tested cameras','checks':[{'id':c['id'],'done':1} for c in job['checklist']],'complete':True})
    check(customer.ok('service',id=service)['item']['warranty_until']=='2027-11-20','Technician update preserves administrator warranty')
    check(technician.call('stock_move',{'product_id':product,'quantity':-1,'reason':'Closed job','service_id':service})[0]==422,'Completed jobs reject new stock deductions')
    customer.ok('message_send',{'body':'Hello from customer'})
    admin.ok('message_send',{'customer_id':cid,'body':'Hello from office'})
    check(len(customer.ok('messages')['items'])==2,'Customer and admin communicate in the same thread')
    check(len(other.ok('messages',customer_id=cid)['items'])==0,'Customer cannot select another customer thread')
    check(all(m['read_at'] for m in admin.ok('messages',customer_id=cid)['items']),'Message read receipts update')
    check(admin.ok('message_unread')['unread']==0,'Reading messages clears office unread count')
    customer.ok('message_send',{'body':'Unread count fixture'})
    check(admin.ok('message_unread')['unread']==1 and staff.ok('message_unread')['unread']==1,'Office shares unread customer count')
    check(other.ok('message_unread')['unread']==0,'Unread counts do not expose other conversations')
    staff.ok('messages',customer_id=cid)
    check(admin.ok('message_unread')['unread']==0,'Staff read clears shared office badge')
    admin.ok('message_send',{'customer_id':cid,'body':'Unread reply fixture'})
    check(customer.ok('message_unread')['unread']==1,'Customer sees unread office reply')
    customer.ok('messages')
    check(customer.ok('message_unread')['unread']==0,'Customer reading clears badge')
    check(technician.call('message_unread')[0]==403,'Technicians cannot retrieve message counts')
    customer.ok('logout',{})
    customer.ok('session')
    check(customer.call('login',{'email':'customer@test.local','password':'wrong'})[0]==401,'Failed sign-in remains rejected')
    customer.login('customer@test.local','Customer-Test-123')
    entries=admin.ok('user_logs',search='Test Customer')['items']
    check({'Signed in','Signed out','Registered account','Failed sign in'} <= {x['action'] for x in entries},'User logs record account lifecycle')
    check(admin.ok('audit',search='no-such-log-unique')['total']==0,'Audit search filters on the server')
    check(admin.ok('audit',page=999999)['page']<=admin.ok('audit')['pages'],'Log pagination clamps to last page')
    for client in [customer,staff,technician]:
        check(client.call('user_logs')[0]==403 and client.call('audit')[0]==403,'Logs restricted to administrators')
    php("require 'app/bootstrap.php';$id=(int)stream_get_contents(STDIN);for($i=0;$i<55;$i++)query('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$id,'Signed in','Grouping fixture']);query('INSERT INTO audit_logs(user_id,action,details) VALUES(NULL,?,?)',['Failed sign in','Unassigned grouping fixture']);",str(cid))
    for log_kind in ['user_logs','audit']:
        grouped=admin.ok(log_kind,view='users')
        group=next(g for g in grouped['groups'] if g['user_id']==cid)
        detail=admin.ok(log_kind,user_id=cid)
        check(int(group['record_count'])==detail['total'] and detail['total']>50,'Grouped '+log_kind+' counts all records before pagination')
        check(len([g for g in grouped['groups'] if g['user_id']==cid])==1,'Grouped '+log_kind+' lists each user once')
        second=admin.ok(log_kind,user_id=cid,page=2)
        check(second['page']==2 and second['items'] and all(x['user_id']==cid for x in detail['items']+second['items']),'Selected '+log_kind+' pages contain only that user')
        check(not ({x['id'] for x in detail['items']} & {x['id'] for x in second['items']}),'Selected '+log_kind+' pages do not overlap')
        check(len(admin.ok(log_kind,view='users',search='customer@test.local')['groups'])==1,'Grouped '+log_kind+' supports account email search')
        check(admin.ok(log_kind,user_id=cid,search='Grouping fixture')['total']==55,'Selected '+log_kind+' search stays within account')
        check(admin.ok(log_kind,user_id='unassigned')['items'][0]['user_id'] is None,'Unassigned '+log_kind+' history is accessible')
        check(admin.call(log_kind,user_id='invalid')[0]==422,'Invalid '+log_kind+' account rejected')
        for client in [customer,staff,technician]:
            check(client.call(log_kind,view='users')[0]==403 and client.call(log_kind,user_id=cid)[0]==403,'Grouped '+log_kind+' remains admin-only')
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jgXkAAAAASUVORK5CYII=')
    boundary='----Test'+secrets.token_hex(8)
    def upload(content,name):
        body=(f'--{boundary}\r\nContent-Disposition: form-data; name="title"\r\n\r\nTest home\r\n--{boundary}\r\nContent-Disposition: form-data; name="blueprint"; filename="{name}"\r\nContent-Type: image/png\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode())
        status,raw,_=customer.request('api.php?action=blueprint_upload',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':customer.token})
        return status,json.loads(raw)
    status,result=upload(b'<?php echo "malicious"; ?>','image.php')
    check(status==422,'Non-image upload rejected despite claimed MIME')
    status,result=upload(png,'plan.png');check(status==200,'Valid image upload accepted')
    bid=result['id']
    check(customer.request(f'blueprint-image.php?id={bid}')[0]==200,'Owner can retrieve private blueprint image')
    check(other.request(f'blueprint-image.php?id={bid}')[0]==404,'Other customer cannot retrieve blueprint image')
    check(other.call('blueprint',id=bid)[0]==404,'Other customer cannot read blueprint metadata')
    check(customer.call('blueprint_analyze',{'id':bid,'consent':'yes'})[0]==503,'Missing AI key reports explicit unavailable state')
    check(customer.call('blueprint_analyze',{'id':bid,'consent':'yes','provider':'unknown'})[0]==422,'Invalid AI provider rejected before any external request')
    check(customer.ok('blueprint',id=bid)['item']['analysis'] is None,'Missing AI key never fabricates analysis')
    # Explicitly marked fixture tests the saved result without making an AI call.
    BLUEPRINT_ID=bid
    fixture={'is_floor_plan':True,'summary':'Automated test fixture, not an AI analysis. Two cameras illustrate the saved-plan interface.','cameras':[{'label':'Front entrance','type':'outdoor','x':12,'y':80,'direction':0,'reason':'Test entry approach.'},{'label':'Shared hallway','type':'indoor','x':58,'y':35,'direction':90,'reason':'Test shared circulation.'}],'warnings':['Fixture only. An actual site survey is required.']}
    php("require 'app/bootstrap.php';require 'app/planner.php';$d=json_decode(stream_get_contents(STDIN),true);$p=validate_plan($d['plan']);$e=build_estimate($p,settings(),query('SELECT * FROM products WHERE active=1')->fetchAll());query(\"UPDATE blueprints SET status='Analyzed',analysis=?,estimate=? WHERE id=?\",[json_encode($p),json_encode($e),$d['id']]);",json.dumps({'id':bid,'plan':fixture}))
    check(customer.ok('blueprint',id=bid)['item']['estimate']['total']==20845,'Saved plan retrieves verified PHP estimate')
    pixels=bytearray([246,248,242]*800*500)
    for x1,y1,x2,y2 in [(65,50,730,58),(65,440,730,448),(65,50,73,448),(722,50,730,448),(320,50,328,210),(320,270,328,448),(65,240,255,248),(420,240,730,248),(510,50,518,248)]:
        for y in range(y1,y2):
            for x in range(x1,x2):
                offset=(y*800+x)*3;pixels[offset:offset+3]=bytes([67,86,76])
    def chunk(kind,data):
        return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
    fixture_png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',800,500,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b''.join(b'\0'+pixels[y*2400:(y+1)*2400] for y in range(500))))+chunk(b'IEND',b'')
    filename=php("require 'app/bootstrap.php';echo query('SELECT filename FROM blueprints WHERE id=?',[(int)stream_get_contents(STDIN)])->fetchColumn();",str(bid))
    (ROOT/'storage'/'blueprints'/filename).write_bytes(fixture_png)
    csv=admin.request('report.php?type=inventory&format=csv')
    check(csv[0]==200 and b"'=SUM(1,1)" in csv[1],'CSV export neutralizes formula injection')
    check(customer.request('report.php?type=inventory')[0]==403,'Customer cannot export business reports')
    for report in ['inventory','low-stock','services','schedule','movements']:
        check(admin.request('report.php?type='+report)[0]==200,'Printable report '+report)
    from advanced_checks import advanced_checks
    advanced_checks(Client, php, check, admin, customer, other, technician, tid, bid, ENV)
    from blueprint_delete import blueprint_delete_checks
    blueprint_delete_checks(Client, php, check, admin, customer, other, technician, bid, PORT, PASSWORD)
    recovery_checks(Client, php, check, MAILBOX)
    audit_checks(Client, php, check, admin)
    # The browser uses the same isolated database; no real records are touched.
    if '--advanced-browser' in sys.argv:
        sys.path.insert(0,str(ROOT/'.tools'))
        from playwright.sync_api import sync_playwright
        from advanced_browser import advanced_browser
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
            artifacts=ROOT/'tests'/'.artifacts';artifacts.mkdir(exist_ok=True)
            advanced_browser(browser, PORT, PASSWORD, BLUEPRINT_ID, check, artifacts)
            browser.close()
    if '--browser' in sys.argv:
        browser_checks()
        admin=Client();admin.login('admin@ncpcs.local',PASSWORD)
    google_checks(Client, php, check)
    other_id=other.ok('session')['user']['id']
    admin.ok('user_save',{'id':other_id,'name':'Other Customer','email':'other@test.local','role':'customer','active':0})
    check(other.call('services')[0]==401,'Deactivated account loses access immediately')
    check(admin.call('user_save',{'id':1,'name':'Admin','email':'admin@ncpcs.local','role':'admin','active':0})[0]==422,'Administrator cannot deactivate own account')
    print(f'ALL {checks} CHECKS PASSED',flush=True)
finally:
    if MAILBOX:
        MAILBOX.close()
    if SERVER:
        SERVER.terminate()
        SERVER.wait(timeout=10)
        server_log.close()
    if UPLOAD_TEMP:
        UPLOAD_TEMP.cleanup()
    # Cleanup is limited to this run's randomly named test database and its uploads.
    if re.fullmatch(r'ncpcs_test_[0-9a-f]{10}',DB):
        try:
            php("require 'app/bootstrap.php';require 'app/Inventory/images.php';foreach(query('SELECT filename FROM product_images')->fetchAll() as $i){delete_product_image_file($i['filename']);} foreach(query('SELECT filename FROM job_photos')->fetchAll() as $j){$jp=__DIR__.'/storage/jobs/'.$j['filename'];if(is_file($jp))unlink($jp);} foreach(query('SELECT filename FROM blueprints')->fetchAll() as $b){$p=__DIR__.'/storage/blueprints/'.$b['filename'];if(is_file($p))unlink($p);} db()->exec('DROP DATABASE `'.getenv('DB_NAME').'`');")
        except Exception as error:
            print('Cleanup warning:',error)
