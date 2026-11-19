<?php
declare(strict_types=1);
function notification_categories(): array {return ['service','appointment','assignment','message','quotation','inventory','warranty','blueprint','security'];}
function notify_user(int $uid,string $category,string $title,string $body,string $target,string $key): void {
    $p=query('SELECT in_app,email FROM notification_preferences WHERE user_id=? AND category=?',[$uid,$category])->fetch();
    if($p && !$p['in_app'] && !$p['email'])return;
    query('INSERT IGNORE INTO notifications(user_id,category,title,body,target,event_key,read_at) VALUES(?,?,?,?,?,?,?)',[$uid,$category,$title,$body,$target,$key,($p && !$p['in_app'])?date('Y-m-d H:i:s'):null]);
    if($p && $p['email']){ $nid=query('SELECT id FROM notifications WHERE user_id=? AND event_key=?',[$uid,$key])->fetchColumn();query('INSERT IGNORE INTO notification_mail(notification_id) VALUES(?)',[$nid]); }
}
function notify_office(string $category,string $title,string $body,string $target,string $key): void {
    foreach(query("SELECT id FROM users WHERE active=1 AND role IN ('admin','staff')")->fetchAll() as $u)notify_user((int)$u['id'],$category,$title,$body,$target,$key);
}
function service_event(array $s,int $actor,string $description,?string $status=null,string $category='service'): void {
    query('INSERT INTO service_events(service_id,user_id,description,status) VALUES(?,?,?,?)',[$s['id'],$actor,$description,$status]);
    $key='service-event:'.db()->lastInsertId();$title='SR-'.str_pad((string)$s['id'],4,'0',STR_PAD_LEFT).' updated';
    notify_user((int)$s['customer_id'],$category,$title,$description,'services/'.$s['id'],$key);
    if($s['technician_id'])notify_user((int)$s['technician_id'],$category,$title,$description,'services/'.$s['id'],$key);
    notify_office($category,$title,$description,'services/'.$s['id'],$key);
}
function strict_date(string $value,bool $time=false): string {
    $value=str_replace('T',' ',$value);$format=$time?(strlen($value)===16?'Y-m-d H:i':'Y-m-d H:i:s'):'Y-m-d';
    $date=DateTimeImmutable::createFromFormat('!'.$format,$value);$errors=DateTimeImmutable::getLastErrors();
    if(!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format($format)!==$value)fail('Enter a valid date'.($time?' and time':'').'.');
    return $date->format($time?'Y-m-d H:i:s':'Y-m-d');
}
function service_transition(string $from,string $to,bool $technician=false): void {
    $allowed=['Pending'=>['Approved','Revision Required','Scheduled','Cancelled'],'Revision Required'=>['Pending','Approved','Cancelled'],'Approved'=>['Scheduled','Revision Required','Cancelled'],'Scheduled'=>['In Progress','Cancelled'],'In Progress'=>['Completed','Cancelled'],'Completed'=>[],'Cancelled'=>[]];
    if($technician && !in_array($to,['In Progress','Completed'],true))fail('Technicians may start or complete assigned work.',403);
    if($from!==$to && !in_array($to,$allowed[$from]??[],true))fail('That status change is not allowed. Refresh the service and follow the next step.',409);
    if(in_array($from,['Completed','Cancelled'],true))fail('This service is closed.',409);
}
function request_key(array $data): string {
    $key=strval_field($data,'request_key',80);if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$key))fail('Refresh this form before submitting.');return $key;
}
function check_revision(array $data,int $revision,string $key='revision'): void {
    if(integer_field($data,$key)!==$revision)fail('This record changed in another window. Reload it before saving.',409);
}
