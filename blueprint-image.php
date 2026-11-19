<?php
require __DIR__.'/app/bootstrap.php';
try {
    $u=require_user(['admin','staff','customer']);$b=accessible_blueprint(integer_field($_GET,'id',1),$u);
    if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$b['filename']))fail('Image not found.',404);
    $file=__DIR__.'/storage/blueprints/'.$b['filename'];
    if(!is_file($file))fail('Image not found.',404);
    header('Content-Type: '.$b['mime']);header('Content-Length: '.filesize($file));
    header('Content-Disposition: inline; filename="blueprint-'.$b['id'].'.'.pathinfo($file,PATHINFO_EXTENSION).'"');
    session_write_close();readfile($file);
}catch(AppError $e){http_response_code($e->getCode() ?: 422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}catch(Throwable $e){error_log('Blueprint image unavailable: '.get_class($e));http_response_code(503);echo 'Image temporarily unavailable. Please try again later.';}
