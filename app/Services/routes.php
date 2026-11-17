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
        db()->beginTransaction();
        query('INSERT INTO services(customer_id,type,title,description,address) VALUES(?,?,?,?,?)',[$cid,$type,strval_field($data,'title',160),strval_field($data,'description',5000),strval_field($data,'address',500)]);
        $id=(int)db()->lastInsertId();query('INSERT INTO service_events(service_id,user_id,description) VALUES(?,?,?)',[$id,$u['id'],'Service request created']);db()->commit();json_out(['id'=>$id]);
    case 'service':
        $id=integer_field($data,'id',1);$s=accessible_service($id,$u);
        json_out(['item'=>$s,'events'=>query('SELECT e.*,u.name FROM service_events e JOIN users u ON u.id=e.user_id WHERE service_id=? ORDER BY e.id DESC',[$id])->fetchAll(),'parts'=>query('SELECT m.*,p.name FROM stock_movements m JOIN products p ON p.id=m.product_id WHERE service_id=? ORDER BY m.id DESC',[$id])->fetchAll()]);
    case 'service_update':
        require_user(['admin','staff','technician']);$id=integer_field($data,'id',1);
        db()->beginTransaction();$old=accessible_service($id,$u,true);
        $status=strval_field($data,'status',30);if(!in_array($status,['Pending','Scheduled','In Progress','Completed','Cancelled'])) fail('Invalid status.');
        if($u['role']==='technician' && !in_array($status,['In Progress','Completed'])) fail('Technicians can start or complete assigned work.',403);
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
                if(query("SELECT id FROM services WHERE technician_id=? AND id<>? AND status NOT IN ('Cancelled','Completed') AND scheduled_at<? AND DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE)>? LIMIT 1",[$tid,$id,$end,$start])->fetch()) fail('This technician already has an overlapping appointment.');
            }
        }
        $warranty=$staff?strval_field($data,'warranty_until',10,false):($old['warranty_until'] ?? '');
        if($warranty){$dt=DateTime::createFromFormat('!Y-m-d',$warranty);if(!$dt || $dt->format('Y-m-d')!==$warranty) fail('Invalid warranty date.');}
        $notes=strval_field($data,'notes',5000,false);
        query('UPDATE services SET status=?,technician_id=?,scheduled_at=?,duration_minutes=?,notes=?,warranty_until=? WHERE id=?',[$status,$tid,$start,$duration,$notes,$warranty ?: null,$id]);
        query('INSERT INTO service_events(service_id,user_id,description) VALUES(?,?,?)',[$id,$u['id'],"Status: $status. ".($start?"Appointment: $start. ":'').$notes]);
        audit('Updated service',(string)$id);db()->commit();json_out(['ok'=>true]);
    default: fail('Action not found.',404);
    }
}
