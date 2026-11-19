<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
try{
    $u=require_user();$p=query('SELECT * FROM job_photos WHERE id=?',[integer_field($_GET,'id',1)])->fetch();if(!$p)fail('Image not found.',404);accessible_service((int)$p['service_id'],$u);
    $path=__DIR__.'/storage/jobs/'.$p['filename'];if(!is_file($path))fail('Image not found.',404);
    header('Content-Type: '.$p['mime']);header('Content-Disposition: inline; filename="service-photo"');header('Content-Length: '.filesize($path));readfile($path);
}catch(AppError $e){http_response_code($e->getCode()?:422);echo 'Image unavailable.';}catch(Throwable $e){http_response_code(500);echo 'Image unavailable.';}
