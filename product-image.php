<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/Inventory/images.php';
try {
    require_user(['admin','staff','technician']);
    $id=integer_field($_GET,'id',1);
    $image=query('SELECT i.* FROM product_images i JOIN products p ON p.id=i.product_id WHERE p.id=? AND p.active=1',[$id])->fetch();
    if (!$image || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$image['filename'])) fail('Picture not found.',404);
    $path=product_image_directory().'/'.$image['filename'];
    if (!is_file($path)) fail('Picture not found.',404);
    header('Content-Type: '.$image['mime']);
    header('Content-Length: '.filesize($path));
    header('Content-Disposition: inline; filename="product-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"');
    session_write_close();
    readfile($path);
} catch (AppError $error) {
    http_response_code($error->getCode() ?: 422);
    echo htmlspecialchars($error->getMessage(),ENT_QUOTES,'UTF-8');
} catch (Throwable $error) {
    error_log((string)$error);
    http_response_code(500);
    echo 'Picture unavailable.';
}
