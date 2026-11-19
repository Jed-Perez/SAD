<?php
declare(strict_types=1);
function handle_services_action(string $action,array $data,array $u,bool $staff): never {
    switch ($action) {
    case 'services':
        $where='';$args=[];
        if($u['role']==='customer'){$where=' WHERE s.customer_id=?';$args=[$u['id']];}
        if($u['role']==='technician'){$where=' WHERE s.technician_id=?';$args=[$u['id']];}
        json_out(['items'=>query('SELECT s.*,c.name customer_name,t.name technician_name FROM services s JOIN users c ON c.id=s.customer_id LEFT JOIN users t ON t.id=s.technician_id'.$where.' ORDER BY s.created_at DESC',$args)->fetchAll()]);
    case 'service_create':
        require_user(['admin','staff','customer']);
        $cid=$staff?integer_field($data,'customer_id',1):$u['id'];
        if(!query("SELECT id FROM users WHERE id=? AND role='customer' AND active=1",[$cid])->fetch()) fail('Choose an active customer.');
        $type=strval_field($data,'type',30);if(!in_array($type,['Installation','Repair','Maintenance'])) fail('Invalid service type.');
        $key=request_key($data);db()->beginTransaction();query('SELECT id FROM users WHERE id=? FOR UPDATE',[$cid]);
        $duplicate=query('SELECT id FROM services WHERE customer_id=? AND request_key=? FOR UPDATE',[$cid,$key])->fetchColumn();if($duplicate){db()->commit();json_out(['id'=>(int)$duplicate,'duplicate'=>true]);}
        query('INSERT INTO services(customer_id,type,title,description,address,request_key) VALUES(?,?,?,?,?,?)',[$cid,$type,strval_field($data,'title',160),strval_field($data,'description',5000),strval_field($data,'address',500),$key]);
        $id=(int)db()->lastInsertId();service_event(accessible_service($id,$u),(int)$u['id'],'Service request submitted.','Pending');db()->commit();json_out(['id'=>$id]);
    case 'service':
        $id=integer_field($data,'id',1);$s=accessible_service($id,$u);
        $s['customer_name']=query('SELECT name FROM users WHERE id=?',[$s['customer_id']])->fetchColumn();$s['technician_name']=$s['technician_id']?query('SELECT name FROM users WHERE id=?',[$s['technician_id']])->fetchColumn():null;
        json_out(['item'=>$s,'events'=>query('SELECT e.*,u.name FROM service_events e JOIN users u ON u.id=e.user_id WHERE service_id=? ORDER BY e.id DESC',[$id])->fetchAll(),'parts'=>query('SELECT m.*,p.name FROM stock_movements m JOIN products p ON p.id=m.product_id WHERE service_id=? ORDER BY m.id DESC',[$id])->fetchAll()]);
    case 'service_update':
        require_user(['admin','staff','technician']);$id=integer_field($data,'id',1);
        db()->beginTransaction();$old=accessible_service($id,$u,true);
        $status=strval_field($data,'status',30);if(!in_array($status,['Pending','Approved','Revision Required','Scheduled','In Progress','Completed','Cancelled'])) fail('Invalid status.');
        if($u['role']==='technician' && !in_array($status,['In Progress','Completed'])) fail('Technicians can start or complete assigned work.',403);
        service_transition($old['status'],$status,$u['role']==='technician');
        if(array_key_exists('revision',$data))check_revision($data,(int)$old['revision']);
        $tid=$staff?(integer_field($data,'technician_id') ?: null):$old['technician_id'];
        $start=$staff?strval_field($data,'scheduled_at',30,false):($old['scheduled_at'] ?? '');
        $duration=$staff?integer_field($data,'duration_minutes',30,480):(int)$old['duration_minutes'];
        $start=$start?str_replace('T',' ',$start):null;
        if($start){$dt=DateTime::createFromFormat(strlen($start)===16?'!Y-m-d H:i':'!Y-m-d H:i:s',$start);$errors=DateTime::getLastErrors();if(!$dt || ($errors && ($errors['warning_count']||$errors['error_count'])))fail('Invalid appointment date.');$start=$dt->format('Y-m-d H:i:s');}
        if(in_array($status,['Scheduled','In Progress']) && (!$start || !$tid)) fail('Assign a technician and appointment first.');
        if($tid){
            // Lock the technician row so simultaneous bookings serialize.
            if(!query("SELECT id FROM users WHERE id=? AND role='technician' AND active=1 FOR UPDATE",[$tid])->fetch()) fail('Choose an active technician.');
            if($start && !in_array($status,['Cancelled','Completed'])){
                $end=date('Y-m-d H:i:s',strtotime($start)+$duration*60);
                if(!slot_available((int)$tid,$start,$duration,$id,0,false))fail('The technician is unavailable, on leave, or already reserved.');
                if(query("SELECT id FROM services WHERE technician_id=? AND id<>? AND status NOT IN ('Cancelled','Completed') AND scheduled_at<? AND DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE)>? LIMIT 1",[$tid,$id,$end,$start])->fetch()) fail('This technician already has an overlapping appointment.');
            }
        }
        $warranty=$staff?strval_field($data,'warranty_until',10,false):($old['warranty_until'] ?? '');
        if($warranty){$dt=DateTime::createFromFormat('!Y-m-d',$warranty);if(!$dt || $dt->format('Y-m-d')!==$warranty) fail('Invalid warranty date.');}
        $notes=strval_field($data,'notes',5000,in_array($status,['Cancelled','Revision Required'],true));
        $eta=$staff?strval_field($data,'estimated_completion',19,false):($old['estimated_completion']??'');if($eta)$eta=strict_date($eta,true);
        if($status==='Completed'){ensure_job($old);if(query('SELECT COUNT(*) FROM job_checklist WHERE service_id=? AND done=0',[$id])->fetchColumn())fail('Finish the job checklist before completing this service.');query('UPDATE job_cards SET completed_at=COALESCE(completed_at,NOW()) WHERE service_id=?',[$id]);}
        query('UPDATE services SET status=?,technician_id=?,scheduled_at=?,duration_minutes=?,notes=?,warranty_until=?,estimated_completion=?,revision=revision+1 WHERE id=?',[$status,$tid,$start,$duration,$notes,$warranty ?: null,$eta?:null,$id]);
        if($status==='Scheduled' && in_array($old['status'],['Pending','Revision Required'],true))service_event($old,(int)$u['id'],'Request approved by office.','Approved');
        if($old['technician_id'] && $old['technician_id']!=$tid)notify_user((int)$old['technician_id'],'assignment','Assignment changed','SR-'.$id.' has been reassigned.','schedule','reassigned:'.$id.':'.($old['revision']+1));
        $old['technician_id']=$tid;service_event($old,(int)$u['id'],"Status: $status. ".($start?"Appointment: $start. ":'').$notes,$status,$start?'appointment':'service');
        if($status==='Cancelled')query("UPDATE appointment_requests SET status='Cancelled',reason=? WHERE service_id=? AND status='Pending'",[$notes,$id]);
        audit('Updated service',(string)$id);db()->commit();json_out(['ok'=>true]);
    default: fail('Action not found.',404);
    }
}
