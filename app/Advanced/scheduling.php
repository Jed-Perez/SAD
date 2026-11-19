<?php
declare(strict_types=1);
function slot_available(int $tid,string $start,int $duration,int $exceptService=0,int $exceptRequest=0,bool $strict=true): bool {
    $lock=db()->inTransaction()?' FOR UPDATE':'';
    $day=(int)date('w',strtotime($start));
    $hours=query('SELECT * FROM technician_hours WHERE technician_id=? AND weekday=?'.$lock,[$tid,$day])->fetch();
    if(!$hours && $strict)return false;
    $buffer=(int)($hours['buffer_minutes']??0);$end=date('Y-m-d H:i:s',strtotime($start)+($duration+$buffer)*60);
    $begin=date('Y-m-d H:i:s',strtotime($start)-$buffer*60);
    if($hours && (substr($begin,0,10)!==substr($end,0,10) || substr($begin,11)<$hours['start_time'] || substr($end,11)>$hours['end_time']))return false;
    if(query('SELECT id FROM technician_time_off WHERE technician_id=? AND starts_at<? AND ends_at>? LIMIT 1'.$lock,[$tid,$end,$begin])->fetch())return false;
    if(query("SELECT id FROM services WHERE technician_id=? AND id<>? AND status NOT IN ('Cancelled','Completed') AND scheduled_at<? AND DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE)>? LIMIT 1".$lock,[$tid,$exceptService,$end,$begin])->fetch())return false;
    return !query("SELECT id FROM appointment_requests WHERE technician_id=? AND id<>? AND service_id<>? AND status='Pending' AND starts_at<? AND DATE_ADD(starts_at,INTERVAL duration_minutes MINUTE)>? LIMIT 1".$lock,[$tid,$exceptRequest,$exceptService,$end,$begin])->fetch();
}
function service_duration(string $type): int {
    if(!in_array($type,['Installation','Repair','Maintenance'],true))fail('Choose a supported service type.');
    return max(30,min(480,(int)(settings()['duration_'.$type]??120)));
}
function lock_technician(int $tid): void {
    if(!query("SELECT id FROM users WHERE id=? AND role='technician' AND active=1 FOR UPDATE",[$tid])->fetch())fail('Choose an active technician.');
}
function available_slots(string $first,string $last,string $type): array {
    $duration=service_duration($type);$techs=query("SELECT id,name FROM users WHERE active=1 AND role='technician'")->fetchAll();
    $hours=[];foreach(query('SELECT * FROM technician_hours')->fetchAll() as $h)$hours[$h['technician_id']][$h['weekday']]=$h;
    $blocked=[];
    foreach(query("SELECT technician_id,scheduled_at starts_at,DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE) ends_at FROM services WHERE status NOT IN ('Completed','Cancelled') AND scheduled_at<? AND DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE)>?",[$last.' 23:59:59',$first.' 00:00:00'])->fetchAll() as $r)$blocked[$r['technician_id']][]=[strtotime($r['starts_at']),strtotime($r['ends_at'])];
    foreach(query("SELECT technician_id,starts_at,DATE_ADD(starts_at,INTERVAL duration_minutes MINUTE) ends_at FROM appointment_requests WHERE status='Pending' AND starts_at<? AND DATE_ADD(starts_at,INTERVAL duration_minutes MINUTE)>?",[$last.' 23:59:59',$first.' 00:00:00'])->fetchAll() as $r)$blocked[$r['technician_id']][]=[strtotime($r['starts_at']),strtotime($r['ends_at'])];
    foreach(query('SELECT technician_id,starts_at,ends_at FROM technician_time_off WHERE starts_at<? AND ends_at>?',[$last.' 23:59:59',$first.' 00:00:00'])->fetchAll() as $r)$blocked[$r['technician_id']][]=[strtotime($r['starts_at']),strtotime($r['ends_at'])];
    $result=[];for($day=$first;$day<=$last;$day=date('Y-m-d',strtotime($day.' +1 day'))){$result[$day]=[];if($day<date('Y-m-d')||$day>date('Y-m-d',strtotime('+180 days')))continue;
        foreach($techs as $t){$h=$hours[$t['id']][(int)date('w',strtotime($day))]??null;if(!$h)continue;$buffer=(int)$h['buffer_minutes']*60;$start=strtotime($day.' '.$h['start_time'])+$buffer;$finish=strtotime($day.' '.$h['end_time']);
            $start=(int)(ceil($start/1800)*1800);
            for(;$start+$duration*60+$buffer<=$finish;$start+=1800){if($start<=time())continue;$ok=true;foreach($blocked[$t['id']]??[] as [$from,$to])if($from<$start+$duration*60+$buffer && $to>$start-$buffer){$ok=false;break;}if($ok)$result[$day][]=['technician_id'=>$t['id'],'technician_name'=>$t['name'],'starts_at'=>date('Y-m-d H:i:s',$start),'duration_minutes'=>$duration];}
        }
    }return $result;
}
function handle_scheduling(string $action,array $data,array $u): never {
    $staff=in_array($u['role'],['admin','staff'],true);
    if($action==='availability'){
        require_user(['admin','staff','customer']);$type=strval_field($data,'type',30);$duration=service_duration($type);
        if(!empty($data['month'])){$first=strict_date(strval_field($data,'month',7).'-01');$last=date('Y-m-t',strtotime($first));if($last<date('Y-m-d') || $first>date('Y-m-d',strtotime('+180 days')))fail('Choose a month within the booking window.');$days=available_slots($first,$last,$type);json_out(['days'=>array_map('count',$days),'timezone'=>'Asia/Manila']);}
        $day=strict_date(strval_field($data,'date',10));
        if($day<date('Y-m-d') || $day>date('Y-m-d',strtotime('+180 days')))fail('Choose a date within the next 180 days.');
        json_out(['slots'=>available_slots($day,$day,$type)[$day],'timezone'=>'Asia/Manila','duration_minutes'=>$duration]);
    }
    if($action==='schedule_settings'){
        require_user(['admin','staff','technician']);$where=$staff?'':' WHERE technician_id=?';$args=$staff?[]:[$u['id']];
        json_out(['hours'=>query('SELECT * FROM technician_hours'.$where.' ORDER BY technician_id,weekday',$args)->fetchAll(),'time_off'=>query('SELECT * FROM technician_time_off'.$where.' ORDER BY starts_at DESC LIMIT 200',$args)->fetchAll(),'durations'=>array_intersect_key(settings(),array_flip(['duration_Installation','duration_Repair','duration_Maintenance']))]);
    }
    if($action==='schedule_settings_save'){
        require_user(['admin','staff']);$tid=integer_field($data,'technician_id',1);db()->beginTransaction();lock_technician($tid);
        if(($data['kind']??'')==='time_off'){
            $start=strict_date(strval_field($data,'starts_at',19),true);$end=strict_date(strval_field($data,'ends_at',19),true);if($end<=$start)fail('Time off must end after it starts.');
            if(query("SELECT id FROM services WHERE technician_id=? AND status IN ('Scheduled','In Progress') AND scheduled_at<? AND DATE_ADD(scheduled_at,INTERVAL duration_minutes MINUTE)>?",[$tid,$end,$start])->fetch() || query("SELECT id FROM appointment_requests WHERE technician_id=? AND status='Pending' AND starts_at<? AND DATE_ADD(starts_at,INTERVAL duration_minutes MINUTE)>?",[$tid,$end,$start])->fetch())fail('Reschedule conflicting appointments before adding time off.',409);
            query('INSERT INTO technician_time_off(technician_id,starts_at,ends_at,reason) VALUES(?,?,?,?)',[$tid,$start,$end,strval_field($data,'reason',255)]);
        }elseif(($data['kind']??'')==='remove_time_off'){
            query('DELETE FROM technician_time_off WHERE id=? AND technician_id=?',[integer_field($data,'id',1),$tid]);
        }else{
            $weekday=integer_field($data,'weekday',0,6);$start=strval_field($data,'start_time',5);$end=strval_field($data,'end_time',5);
            if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$end) || $start>=$end)fail('Enter a valid working time range.');
            query('INSERT INTO technician_hours(technician_id,weekday,start_time,end_time,buffer_minutes) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),buffer_minutes=VALUES(buffer_minutes)',[$tid,$weekday,$start,$end,integer_field($data,'buffer_minutes',0,120)]);
        }audit('Updated technician availability',(string)$tid);db()->commit();json_out(['ok'=>true]);
    }
    if($action==='appointments'){
        $where=$staff?'':($u['role']==='customer'?' WHERE s.customer_id=?':' WHERE a.technician_id=?');
        json_out(['items'=>query('SELECT a.*,s.title,s.customer_id,c.name customer_name,t.name technician_name FROM appointment_requests a JOIN services s ON s.id=a.service_id JOIN users c ON c.id=s.customer_id JOIN users t ON t.id=a.technician_id'.$where.' ORDER BY a.starts_at DESC LIMIT 300',$staff?[]:[$u['id']])->fetchAll()]);
    }
    if($action==='appointment_book'){
        require_user(['admin','staff','customer']);$key=request_key($data);$tid=integer_field($data,'technician_id',1);$start=strict_date(strval_field($data,'starts_at',19),true);
        if(strtotime($start)<=time() || strtotime($start)>strtotime('+180 days'))fail('Choose a future appointment within 180 days.');
        $type=strval_field($data,'type',30);$duration=service_duration($type);db()->beginTransaction();
        // The customer lock also serializes two submissions of the same form key.
        query('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);
        $duplicate=query('SELECT id,service_id FROM appointment_requests WHERE requested_by=? AND request_key=?',[$u['id'],$key])->fetch();
        if($duplicate){db()->commit();json_out($duplicate+['duplicate'=>true]);}
        $sid=integer_field($data,'service_id');
        if($sid){$service=accessible_service($sid,$u,true);if(in_array($service['status'],['Completed','Cancelled','In Progress'],true))fail('This service cannot be rescheduled.');$type=$service['type'];$duration=service_duration($type);if(query("SELECT id FROM appointment_requests WHERE service_id=? AND status='Pending'",[$sid])->fetch())fail('This service already has a pending appointment request.',409);}
        lock_technician($tid);if(!slot_available($tid,$start,$duration,$sid))fail('That slot is no longer available. Choose another time.',409);
        if(!$sid){$cid=$staff?integer_field($data,'customer_id',1):(int)$u['id'];if(!query("SELECT id FROM users WHERE id=? AND role='customer' AND active=1",[$cid])->fetch())fail('Choose an active customer.');
            query('INSERT INTO services(customer_id,type,title,description,address) VALUES(?,?,?,?,?)',[$cid,$type,strval_field($data,'title',160),strval_field($data,'instructions',5000,false),strval_field($data,'address',500)]);$sid=(int)db()->lastInsertId();$service=accessible_service($sid,$u);
            service_event($service,(int)$u['id'],'Service request submitted.','Pending');
        }
        query('INSERT INTO appointment_requests(service_id,requested_by,technician_id,starts_at,duration_minutes,request_key) VALUES(?,?,?,?,?,?)',[$sid,$u['id'],$tid,$start,$duration,$key]);$id=(int)db()->lastInsertId();
        service_event($service,(int)$u['id'],'Appointment requested for '.$start.'. Awaiting office confirmation.',null,'appointment');audit('Requested appointment',(string)$id);db()->commit();json_out(['id'=>$id,'service_id'=>$sid]);
    }
    if($action==='appointment_update'){
        require_user(['admin','staff','customer']);$id=integer_field($data,'id',1);$status=strval_field($data,'status',20);if(!in_array($status,['Approved','Cancelled'],true))fail('Choose approve or cancel.');if(!$staff && $status!=='Cancelled')fail('Only office staff can confirm appointments.',403);
        db()->beginTransaction();$initial=query('SELECT service_id FROM appointment_requests WHERE id=?',[$id])->fetchColumn();if(!$initial)fail('Appointment not found.',404);
        $s=accessible_service((int)$initial,$u,true);$a=query('SELECT * FROM appointment_requests WHERE id=? FOR UPDATE',[$id])->fetch();
        if($a['status']!=='Pending')fail('This appointment request has already been handled.',409);
        if(in_array($s['status'],['Completed','Cancelled','In Progress'],true))fail('This service cannot be scheduled.',409);
        $tid=integer_field($data,'technician_id') ?: (int)$a['technician_id'];lock_technician($tid);
        $reason=strval_field($data,'reason',1000,$status==='Cancelled');
        if($status==='Approved'){
            $start=empty($data['starts_at'])?$a['starts_at']:strict_date(strval_field($data,'starts_at',19),true);
            if(strtotime($start)<=time() || !slot_available($tid,$start,(int)$a['duration_minutes'],(int)$s['id'],$id))fail('This slot is no longer available.',409);
            query("UPDATE services SET status='Scheduled',scheduled_at=?,technician_id=?,duration_minutes=?,revision=revision+1 WHERE id=?",[$start,$tid,$a['duration_minutes'],$s['id']]);
            query('UPDATE appointment_requests SET starts_at=?,technician_id=? WHERE id=?',[$start,$tid,$id]);
            if(in_array($s['status'],['Pending','Revision Required'],true))service_event($s,(int)$u['id'],'Request approved by office.','Approved');
            $s['technician_id']=$tid;service_event($s,(int)$u['id'],'Appointment confirmed for '.$start.'. '.$reason,'Scheduled','assignment');
        }else service_event($s,(int)$u['id'],'Appointment request cancelled: '.$reason,null,'appointment');
        query('UPDATE appointment_requests SET status=?,reason=? WHERE id=?',[$status,$reason,$id]);audit('Updated appointment',"$id: $status");db()->commit();json_out(['ok'=>true]);
    }
    fail('Action not found.',404);
}
