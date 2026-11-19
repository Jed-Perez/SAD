<?php
declare(strict_types=1);

function delivery_settings(): array {
    global $config;
    $settings=$config;
    foreach(['MAIL_TRANSPORT'=>'mail_transport','SMTP_HOST'=>'smtp_host','SMTP_PORT'=>'smtp_port','SMTP_ENCRYPTION'=>'smtp_encryption','SMTP_USERNAME'=>'smtp_username','SMTP_PASSWORD'=>'smtp_password'] as $env=>$key){
        if(getenv($env)!==false)$settings[$key]=getenv($env);
    }
    return $settings;
}

function delivery_configured(array $settings): bool {
    $transport=$settings['mail_transport']??'mail';
    if($transport==='mail')return function_exists('mail');
    if($transport!=='smtp')return false;
    $host=(string)($settings['smtp_host']??'');
    return $host!=='' && (bool)preg_match('/^[a-zA-Z0-9.-]+$/D',$host)
        && filter_var($settings['smtp_port']??465,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]])!==false
        && in_array($settings['smtp_encryption']??'ssl',['ssl','tls'],true)
        && trim((string)($settings['smtp_username']??''))!==''
        && trim((string)($settings['smtp_password']??''))!=='';
}

function send_account_email(string $to,string $subject,string $body): bool {
    $settings=delivery_settings();
    if(!delivery_configured($settings)){
        error_log('Email delivery unavailable: check server mail configuration.');
        return false;
    }
    try{
        if(($settings['mail_transport']??'mail')==='mail'){
            return @mail($to,$subject,$body,['From'=>$settings['mail_from'],'Content-Type'=>'text/plain; charset=UTF-8']);
        }
        require_once __DIR__.'/PHPMailer/Exception.php';
        require_once __DIR__.'/PHPMailer/SMTP.php';
        require_once __DIR__.'/PHPMailer/PHPMailer.php';
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host=(string)$settings['smtp_host'];
        $mail->Port=(int)($settings['smtp_port']??465);
        $mail->SMTPSecure=$settings['smtp_encryption']??'ssl';
        $mail->SMTPAuth=true;
        $mail->Username=(string)$settings['smtp_username'];
        $mail->Password=(string)$settings['smtp_password'];
        $mail->SMTPDebug=0;
        $mail->Timeout=15;
        $mail->Timelimit=30;
        $mail->CharSet='UTF-8';
        $mail->setFrom((string)$settings['mail_from'],'TechTrack');
        $mail->addAddress($to);
        $mail->Subject=$subject;
        $mail->Body=$body;
        return $mail->send();
    }catch(Throwable $error){
        // SMTP exceptions/debug output can contain credentials or reset links.
        error_log('Email delivery failed. Check SMTP login, TLS connection and provider limits.');
        return false;
    }
}
