<?php
require __DIR__.'/app/bootstrap.php';
try {
    $u=require_user(['admin','staff','customer']);$b=accessible_blueprint(integer_field($_GET,'id',1),$u);
    $file=__DIR__.'/storage/blueprints/'.$b['filename'];
    if(!is_file($file))fail('Image not found.',404);
    header('Content-Type: '.$b['mime']);header('Content-Length: '.filesize($file));
    header('Content-Disposition: inline; filename="blueprint-'.$b['id'].'.'.pathinfo($file,PATHINFO_EXTENSION).'"');
    readfile($file);
}catch(AppError $e){http_response_code($e->getCode() ?: 422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
