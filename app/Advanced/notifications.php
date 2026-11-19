<?php
declare(strict_types=1);
function handle_notifications(string $action,array $data,array $u): never {
    if($action==='notification_preferences')json_out(['categories'=>notification_categories(),'items'=>query('SELECT category,in_app,email FROM notification_preferences WHERE user_id=?',[$u['id']])->fetchAll()]);
    if($action==='notification_preferences_save'){
        $category=strval_field($data,'category',30);if(!in_array($category,notification_categories(),true))fail('Unknown notification category.');
        $app=integer_field($data,'in_app',0,1);$email=integer_field($data,'email',0,1);
        global $config;if($email && (!filter_var($config['mail_from']??'',FILTER_VALIDATE_EMAIL) || empty($config['notification_email_enabled'])))fail('Email reminders have not been enabled by the administrator.');
        if($category==='security' && !$app)fail('Account security notifications must remain enabled.');
        query('INSERT INTO notification_preferences(user_id,category,in_app,email) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE in_app=VALUES(in_app),email=VALUES(email)',[$u['id'],$category,$app,$email]);json_out(['ok'=>true]);
    }
    if($action==='notification_read'){
        $id=integer_field($data,'id');$where=$id?' AND id=?':'';
        query('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?'.$where,$id?[$u['id'],$id]:[$u['id']]);json_out(['ok'=>true]);
    }
    $category=strval_field($data,'category',30,false);$before=integer_field($data,'before',0,PHP_INT_MAX);$where=' WHERE user_id=?';$args=[$u['id']];
    if($category){if(!in_array($category,notification_categories(),true))fail('Unknown notification category.');$where.=' AND category=?';$args[]=$category;}
    if($before){$where.=' AND id<?';$args[]=$before;}
    $items=query('SELECT * FROM notifications'.$where.' ORDER BY id DESC LIMIT 40',$args)->fetchAll();
    foreach($items as &$item){
        if(preg_match('#^services/(\d+)$#D',$item['target'],$match)){
            try{accessible_service((int)$match[1],$u);}catch(AppError $e){$item['target']='services';}
        }
    }unset($item);
    json_out(['items'=>$items,'unread'=>(int)query('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL',[$u['id']])->fetchColumn(),'next'=>count($items)===40?end($items)['id']:null]);
}
function generate_reminders(): void {
    foreach(query("SELECT * FROM services WHERE status='Scheduled' AND scheduled_at>NOW() AND scheduled_at<=DATE_ADD(NOW(),INTERVAL 1 DAY)")->fetchAll() as $s){
        foreach(array_unique(array_filter([$s['customer_id'],$s['technician_id']])) as $uid)notify_user((int)$uid,'appointment','Upcoming service visit','SR-'.$s['id'].' is scheduled for '.$s['scheduled_at'].' (Asia/Manila).','services/'.$s['id'],'reminder:'.$s['id'].':'.$s['scheduled_at']);
    }
    foreach(query("SELECT * FROM services WHERE warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchAll() as $s)notify_user((int)$s['customer_id'],'warranty','Warranty reminder','SR-'.$s['id'].' warranty ends on '.$s['warranty_until'].'.','services/'.$s['id'],'warranty:'.$s['id'].':'.$s['warranty_until']);
    foreach(query('SELECT * FROM products WHERE active=1 AND stock<=reorder_level')->fetchAll() as $p)notify_office('inventory','Low stock: '.$p['name'],$p['stock'].' units remaining. Reorder threshold: '.$p['reorder_level'].'.','forecast','low-stock:'.$p['id'].':'.date('Y-m-d'));
    query('INSERT INTO inventory_snapshots(snapshot_date,inventory_value,total_units) SELECT CURDATE(),COALESCE(SUM(stock*price),0),COALESCE(SUM(stock),0) FROM products WHERE active=1 ON DUPLICATE KEY UPDATE inventory_value=VALUES(inventory_value),total_units=VALUES(total_units)');
    query("UPDATE quotations q JOIN quotation_versions v ON v.quotation_id=q.id AND v.version=q.current_version SET q.status='Expired' WHERE q.status IN ('Sent','Viewed','Revision Requested') AND v.valid_until<CURDATE()");
}
