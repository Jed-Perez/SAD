<?php
declare(strict_types=1);
function save_product(array $data): int {
    $id=integer_field($data,'id');
    $values=[strval_field($data,'sku',60),strval_field($data,'name',160),strval_field($data,'category',60),number_field($data,'price'),integer_field($data,'reorder_level')];
    $remove=integer_field($data,'remove_image',0,1)===1;
    $newImage=null;
    $oldFilename=null;
    db()->beginTransaction();
    try {
        if ($id) {
            if (!query('SELECT id FROM products WHERE id=? AND active=1 FOR UPDATE',[$id])->fetch()) fail('Product not found.',404);
            $oldFilename=query('SELECT filename FROM product_images WHERE product_id=?',[$id])->fetchColumn() ?: null;
        }
        $newImage=receive_product_image();
        if ($id) query('UPDATE products SET sku=?,name=?,category=?,price=?,reorder_level=?,updated_at=NOW() WHERE id=?',[...$values,$id]);
        else {
            query('INSERT INTO products(sku,name,category,price,reorder_level) VALUES(?,?,?,?,?)',$values);
            $id=(int)db()->lastInsertId();
        }
        if ($newImage) {
            query('INSERT INTO product_images(product_id,filename,mime) VALUES(?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),mime=VALUES(mime)',[$id,$newImage['filename'],$newImage['mime']]);
        } elseif ($remove) query('DELETE FROM product_images WHERE product_id=?',[$id]);
        audit('Saved product',(string)$id);
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        if ($newImage) delete_product_image_file($newImage['filename']);
        throw $error;
    }
    if ($newImage || $remove) delete_product_image_file($oldFilename);
    return $id;
}
