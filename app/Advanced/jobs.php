<?php
declare(strict_types=1);
function job_defaults(string $type): array {
    $configured=settings()['checklist_'.$type]??null;if($configured){$items=json_decode($configured,true);if(is_array($items)&&$items)return $items;}
    return match($type){'Installation'=>['Verify site conditions','Confirm camera locations','Prepare equipment','Install camera mounts','Connect cabling','Configure recording device','Test camera connections','Verify recording','Demonstrate operation to customer','Complete installation documentation'],'Repair'=>['Inspect reported fault','Confirm repair scope','Repair or replace approved parts','Test operation','Document repair and explain results'],default=>['Inspect equipment','Clean and check connections','Check recording and storage','Test camera coverage','Document maintenance findings']};
}
function ensure_job(array $s): void {
    if(query('SELECT service_id FROM job_cards WHERE service_id=?',[$s['id']])->fetch())return;
    query("INSERT INTO job_cards(service_id,notes) VALUES(?,'')",[$s['id']]);
    foreach(job_defaults($s['type']) as $label)query('INSERT INTO job_checklist(service_id,label) VALUES(?,?)',[$s['id'],$label]);
}
function handle_jobs(string $action,array $data,array $u): never {
    $sid=integer_field($data,'service_id',1);$s=accessible_service($sid,$u);
    if($action==='job_card'){
        $customer=query('SELECT name,phone,address FROM users WHERE id=?',[$s['customer_id']])->fetch();
        json_out(['service'=>$s,'customer'=>$customer,'card'=>query('SELECT * FROM job_cards WHERE service_id=?',[$sid])->fetch()?:null,'defaults'=>job_defaults($s['type']),'checklist'=>query('SELECT * FROM job_checklist WHERE service_id=? ORDER BY id',[$sid])->fetchAll(),'photos'=>query('SELECT id,phase,caption,created_at FROM job_photos WHERE service_id=? ORDER BY id',[$sid])->fetchAll(),'parts'=>query('SELECT m.quantity,m.reason,p.name FROM stock_movements m JOIN products p ON p.id=m.product_id WHERE service_id=?',[$sid])->fetchAll(),'equipment'=>query("SELECT i.description,i.quantity FROM quotation_items i JOIN quotation_versions v ON v.id=i.version_id JOIN quotations q ON q.id=v.quotation_id WHERE q.service_id=? AND q.status='Approved' AND q.current_version=v.version",[$sid])->fetchAll()]);
    }
    if($action==='job_acknowledge'){
        require_user(['customer']);db()->beginTransaction();$s=accessible_service($sid,$u,true);if($s['status']!=='Completed')fail('Acknowledge the visit after service completion.');
        $card=query('SELECT * FROM job_cards WHERE service_id=? FOR UPDATE',[$sid])->fetch();if(!$card || !$card['completed_at'])fail('The technician must complete the job card first.');
        if(!$card['acknowledged_at']){query('UPDATE job_cards SET acknowledged_by=?,acknowledged_at=NOW() WHERE service_id=?',[$u['id'],$sid]);service_event($s,(int)$u['id'],'Customer acknowledged the recorded visit. This is not payment or legal acceptance.');}db()->commit();json_out(['ok'=>true]);
    }
    require_user(['admin','staff','technician']);
    if($action==='job_photo'){
        $phase=strval_field($data,'phase',20);if(!in_array($phase,['Before','Installation','After'],true))fail('Choose a photo phase.');$caption=strval_field($data,'caption',255,false);
        $file=$_FILES['photo']??null;if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']<1 || $file['size']>5*1024*1024)fail('Choose an image up to 5 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$dimensions=@getimagesize($file['tmp_name']);
        if(!isset($extensions[$mime])||!$dimensions||$dimensions[0]*$dimensions[1]>24000000)fail('Use a valid JPG, PNG or WebP image up to 24 megapixels.');
        db()->beginTransaction();$s=accessible_service($sid,$u,true);if(in_array($s['status'],['Completed','Cancelled'],true))fail('This job is closed.');
        if(query('SELECT COUNT(*) FROM job_photos WHERE service_id=?',[$sid])->fetchColumn()>=40)fail('This job already has forty photographs.');
        $directory=dirname(__DIR__,2).'/storage/jobs';if(!is_dir($directory))mkdir($directory,0700,true);$filename=bin2hex(random_bytes(24)).'.'.$extensions[$mime];$path=$directory.'/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$path))fail('The photo could not be saved.',500);
        try{query('INSERT INTO job_photos(service_id,author_id,filename,mime,phase,caption) VALUES(?,?,?,?,?,?)',[$sid,$u['id'],$filename,$mime,$phase,$caption]);audit('Added job photo',(string)$sid);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();unlink($path);throw $e;}json_out(['ok'=>true]);
    }
    db()->beginTransaction();$s=accessible_service($sid,$u,true);if(in_array($s['status'],['Completed','Cancelled'],true))fail('This job is closed.');ensure_job($s);
    $card=query('SELECT * FROM job_cards WHERE service_id=? FOR UPDATE',[$sid])->fetch();check_revision($data,(int)$card['revision']);
    $notes=strval_field($data,'notes',5000,false);$checks=$data['checks']??[];if(!is_array($checks) || count($checks)>60)fail('Invalid checklist.');
    foreach($checks as $item){if(!is_array($item))fail('Invalid checklist item.');$id=integer_field($item,'id',1);if(!query('SELECT id FROM job_checklist WHERE id=? AND service_id=?',[$id,$sid])->fetch())fail('Checklist item not found.',404);query('UPDATE job_checklist SET done=?,updated_by=?,updated_at=NOW() WHERE id=?',[integer_field($item,'done',0,1),$u['id'],$id]);}
    $add=strval_field($data,'add_item',255,false);if($add){if(query('SELECT COUNT(*) FROM job_checklist WHERE service_id=?',[$sid])->fetchColumn()>=60)fail('Checklist limit reached.');query('INSERT INTO job_checklist(service_id,label) VALUES(?,?)',[$sid,$add]);}
    if(($data['complete']??false)===true){
        service_transition($s['status'],'Completed',$u['role']==='technician');
        if(query('SELECT COUNT(*) FROM job_checklist WHERE service_id=? AND done=0',[$sid])->fetchColumn()>0)fail('Complete every checklist item before finishing this job.');if(!$notes)fail('Add completion notes.');
        query("UPDATE services SET status='Completed',revision=revision+1 WHERE id=?",[$sid]);query('UPDATE job_cards SET completed_at=NOW() WHERE service_id=?',[$sid]);service_event($s,(int)$u['id'],'Service completed. '.$notes,'Completed');
    }
    query('UPDATE job_cards SET notes=?,revision=revision+1 WHERE service_id=?',[$notes,$sid]);audit('Updated job card',(string)$sid);db()->commit();json_out(['ok'=>true]);
}
