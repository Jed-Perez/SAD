<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/notifications.php';
if(!query("SELECT GET_LOCK('techtrack-reminders',0)")->fetchColumn()){echo "Reminder worker already running.\n";exit;}
try{
    generate_reminders();
    if(!empty($config['notification_email_enabled']) && filter_var($config['mail_from']??'',FILTER_VALIDATE_EMAIL)){
        foreach(query("SELECT m.notification_id,n.title,n.body,u.email FROM notification_mail m JOIN notifications n ON n.id=m.notification_id JOIN users u ON u.id=n.user_id JOIN notification_preferences p ON p.user_id=u.id AND p.category=n.category WHERE m.status='Pending' AND m.attempts<3 AND u.active=1 AND p.email=1 LIMIT 50")->fetchAll() as $m){
            // Claim before delivery: an interrupted send is never blindly retried.
            query("UPDATE notification_mail SET status='Sending',attempts=attempts+1 WHERE notification_id=?",[$m['notification_id']]);
            $ok=@mail($m['email'],preg_replace('/[\r\n]+/',' ',$m['title']),$m['body']."\n\nOpen TechTrack to view details.",['From'=>$config['mail_from'],'Content-Type'=>'text/plain; charset=UTF-8']);
            query('UPDATE notification_mail SET status=? WHERE notification_id=?',[$ok?'Sent':'Pending',$m['notification_id']]);
        }
    }
    echo "Notifications and inventory snapshot updated.\n";
}finally{query("SELECT RELEASE_LOCK('techtrack-reminders')");}
