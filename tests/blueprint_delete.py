"""Deletion checks against the integration harness's disposable database."""
import json
import sys
from pathlib import Path


def blueprint_delete_checks(Client, php, check, admin, customer, other, technician, linked_plan, port, password):
    root=Path(__file__).resolve().parents[1]
    owner=customer.ok('session')['user']['id']
    customer.ok('notification_preferences_save',{'category':'blueprint','in_app':1,'email':0})

    def fixture(image=True, status='Edited'):
        return json.loads(php("""require 'app/bootstrap.php';
            $d=json_decode(stream_get_contents(STDIN),true);
            $filename=bin2hex(random_bytes(24)).'.webp';
            query('INSERT INTO blueprints(user_id,title,filename,mime,budget,notes,status) VALUES(?,?,?,?,0,?,?)',[$d['owner'],'Deletion test plan',$filename,'image/webp','Isolated deletion fixture',$d['status']]);
            $id=(int)db()->lastInsertId();
            query('INSERT INTO blueprint_versions(blueprint_id,version,author_id,plan,estimate) VALUES(?,1,?,?,?)',[$id,$d['owner'],'{}','{}']);
            $vid=(int)db()->lastInsertId();
            query('INSERT INTO blueprint_reviews(version_id,author_id,decision,feedback,internal_notes) VALUES(?,?,?,?,?)',[$vid,$d['owner'],'Revision Required','Test review','Test internal note']);
            notify_user($d['owner'],'blueprint','Test plan update','Test notification','planner/'.$id,'delete-test:'.$id);
            if($d['image'])copy('assets/images/products/outdoor-camera.webp','storage/blueprints/'.$filename);
            echo json_encode(['id'=>$id,'filename'=>$filename,'version_id'=>$vid]);
        """,json.dumps({'owner':owner,'image':image,'status':status})))

    def file_for(f):
        return root/'storage'/'blueprints'/f['filename']

    f=fixture()
    check(Client().call('blueprint_delete',{'id':f['id']})[0]==401,'Blueprint deletion requires authentication')
    check(customer.call('blueprint_delete',id=f['id'])[0]==405,'Blueprint deletion rejects GET')
    check(customer.request('api.php?action=blueprint_delete',json.dumps({'id':f['id']}).encode(),{'Content-Type':'application/json'})[0]==419,'Blueprint deletion requires CSRF')
    check(other.call('blueprint_delete',{'id':f['id']})[0]==404,'Customer cannot delete another customer plan')
    check(technician.call('blueprint_delete',{'id':f['id']})[0]==403,'Technician cannot delete plans')
    check(file_for(f).exists(),'Rejected deletion keeps the image')
    check(admin.call('blueprint_delete',{'id':linked_plan})[0]==409,'Quotation-linked blueprint cannot be deleted')
    customer.ok('blueprint_delete',{'id':f['id']})
    check(not file_for(f).exists(),'Owner deletion removes the uploaded image')
    check(customer.call('blueprint',id=f['id'])[0]==404,'Deleted blueprint is no longer accessible')
    counts=json.loads(php("require 'app/bootstrap.php';$d=json_decode(stream_get_contents(STDIN),true);echo json_encode([query('SELECT COUNT(*) FROM blueprint_versions WHERE blueprint_id=?',[$d['id']])->fetchColumn(),query('SELECT COUNT(*) FROM blueprint_reviews WHERE version_id=?',[$d['version_id']])->fetchColumn()]);",json.dumps(f)))
    check(counts==[0,0],'Blueprint deletion removes dependent versions and reviews')
    check(customer.call('blueprint_delete',{'id':f['id']})[0]==404,'Repeated deletion has no second effect')
    check(any(n['event_key']=='delete-test:'+str(f['id']) and n['target']=='planner' for n in customer.ok('notifications')['items']),'Old notifications link to the remaining planner list')
    missing=fixture(image=False)
    admin.ok('blueprint_delete',{'id':missing['id']})
    check(customer.call('blueprint',id=missing['id'])[0]==404,'Administrator can delete a plan with a missing image')
    running=fixture(status='Analyzing')
    check(admin.call('blueprint_delete',{'id':running['id']})[0]==409,'Active AI analysis blocks deletion')
    php("require 'app/bootstrap.php';query(\"UPDATE blueprints SET status='Failed' WHERE id=?\",[(int)stream_get_contents(STDIN)]);",str(running['id']))
    admin.ok('blueprint_delete',{'id':running['id']})
    failed=fixture()
    php("require 'app/bootstrap.php';db()->exec(\"CREATE TRIGGER delete_blueprint_failure BEFORE DELETE ON blueprints FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Intentional deletion failure'\");")
    try:
        check(admin.call('blueprint_delete',{'id':failed['id']})[0]==503,'Database deletion failure returns a safe error')
    finally:
        php("require 'app/bootstrap.php';db()->exec('DROP TRIGGER delete_blueprint_failure');")
    check(file_for(failed).exists(),'Failed deletion restores the original image')
    check(len(customer.ok('plan_workspace',id=failed['id'])['versions'])==1,'Failed deletion rolls back version history')
    admin.ok('blueprint_delete',{'id':failed['id']})

    if '--delete-browser' in sys.argv:
        sys.path.insert(0,str(root/'.tools'))
        from playwright.sync_api import sync_playwright
        f=fixture(image=False)
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
            page=browser.new_page(viewport={'width':390,'height':844})
            errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
            page.goto(f'http://127.0.0.1:{port}/portal.php#planner')
            page.locator('#login-email').fill('admin@ncpcs.local');page.locator('#login-password').fill(password);page.locator('#auth-submit').click()
            button=page.locator(f'[data-action="delete-blueprint"][data-id="{f["id"]}"]')
            button.click()
            page.locator('#modal-body').get_by_text('Deletion test plan',exact=True).wait_for(state='visible')
            check(page.locator('#modal-body').get_by_text('Deletion test plan',exact=True).is_visible(),'Delete confirmation identifies the selected plan')
            page.locator('#editor-form [data-bs-dismiss="modal"]').click();page.locator('#editor-modal').wait_for(state='hidden')
            check(button.is_visible(),'Cancelling confirmation preserves the blueprint card')
            button.click();page.locator('#editor-form [type=submit]').click();page.locator('#editor-modal').wait_for(state='hidden')
            check(button.count()==0,'Confirmed deletion removes the card through AJAX')
            check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Planner delete buttons fit a mobile viewport')
            check(not errors,'Blueprint deletion has no browser runtime errors')
            browser.close()
