<?php
declare(strict_types=1);
function product_image_directory(): string {
    return dirname(__DIR__,2).'/storage/products';
}
function product_image_details(array $product): array {
    $illustrations=[
        'CAM-OUT-4MP'=>'outdoor-camera', 'CAM-IN-4MP'=>'indoor-camera',
        'NVR-8-POE'=>'network-recorder', 'HDD-1TB-SUR'=>'surveillance-drive',
        'CAT6-M'=>'cat6-cable', 'INSTALL-KIT'=>'installation-kit',
        'SSD-500'=>'solid-state-drive', 'RAM-8-DDR4'=>'desktop-memory',
    ];
    $custom=!empty($product['image_filename']);
    $sample=$illustrations[$product['sku']] ?? null;
    $product['has_image']=$custom;
    $product['image_url']=$custom ? 'product-image.php?id='.(int)$product['id'] : ($sample ? 'assets/images/products/'.$sample.'.webp' : null);
    $product['image_is_sample']=!$custom && $sample!==null;
    unset($product['image_filename']);
    return $product;
}
function receive_product_image(): ?array {
    $file=$_FILES['image'] ?? null;
    if ($file===null) return null;
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) fail('Choose a valid product picture.');
    if ($file['error']===UPLOAD_ERR_NO_FILE) return null;
    if ($file['error']!==UPLOAD_ERR_OK || empty($file['size']) || $file['size']>2*1024*1024) fail('Choose a JPG, PNG, or WebP picture up to 2 MB.');
    if (!is_uploaded_file($file['tmp_name'])) fail('Invalid picture upload.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $size=@getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || !$size || $size[0]*$size[1]>12000000) fail('Upload a valid JPG, PNG, or WebP picture up to 12 megapixels.');
    $directory=product_image_directory();
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) fail('Product picture storage is unavailable.',500);
    $filename=bin2hex(random_bytes(24)).'.'.$extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'],$directory.'/'.$filename)) fail('The product picture could not be saved.',500);
    return ['filename'=>$filename,'mime'=>$mime];
}
function delete_product_image_file(?string $filename): void {
    if ($filename && preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$filename)) {
        $path=product_image_directory().'/'.$filename;
        if (is_file($path)) @unlink($path);
    }
}
