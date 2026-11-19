<?php
declare(strict_types=1);
require_once __DIR__.'/images.php';
require_once __DIR__.'/actions.php';
function handle_inventory_action(string $action,array $data,array $u): never {
    switch ($action) {
    case 'products':
        require_user(['admin','staff','technician']);
        $archived=integer_field($data,'archived',0,1);if($archived)require_user(['admin','staff']);
        $products=query('SELECT p.*,i.filename image_filename FROM products p LEFT JOIN product_images i ON i.product_id=p.id WHERE p.active=? ORDER BY p.category,p.name',[$archived?0:1])->fetchAll();
        json_out(['items'=>array_map('product_image_details',$products)]);
    case 'product_save':
        require_user(['admin','staff']);
        json_out(['id'=>save_product($data)]);
    case 'product_archive':
        require_user(['admin','staff']); $id=integer_field($data,'id',1);
        $s=query('UPDATE products SET active=0 WHERE id=? AND stock=0',[$id]);
        if(!$s->rowCount()) fail('Only products with zero stock can be archived.');
        audit('Archived product',(string)$id);json_out(['ok'=>true]);
    case 'product_restore':
        require_user(['admin','staff']);$id=integer_field($data,'id',1);
        if(!query('UPDATE products SET active=1 WHERE id=? AND active=0',[$id])->rowCount())fail('Archived product not found.',404);
        audit('Restored product',(string)$id);json_out(['ok'=>true]);
    case 'stock_move':
        require_user(['admin','staff','technician']);
        $id=integer_field($data,'product_id',1);$qty=integer_field($data,'quantity',-100000,100000);if(!$qty) fail('Quantity cannot be zero.');
        $key=request_key($data);$reason=strval_field($data,'reason',255);$sid=integer_field($data,'service_id') ?: null;
        if($u['role']==='technician' && (!$sid || $qty>0)) fail('Technicians can only record parts used on their assigned service.',403);
        db()->beginTransaction();
        $existing=query('SELECT id,product_id,quantity,service_id FROM stock_movements WHERE user_id=? AND request_key=?',[$u['id'],$key])->fetch();if($existing){if($existing['product_id']!=$id || $existing['quantity']!=$qty || $existing['service_id']!=$sid)fail('This submission key was already used for different parts.',409);db()->commit();json_out(['ok'=>true,'duplicate'=>true]);}
        if($sid){$service=accessible_service($sid,$u,true);if(in_array($service['status'],['Completed','Cancelled'])) fail('This service is closed.');if($qty>0) fail('Parts used must be a stock deduction.');}
        $p=query('SELECT * FROM products WHERE id=? AND active=1 FOR UPDATE',[$id])->fetch();
        if(!$p) fail('Product not found.',404);
        $existing=query('SELECT id,product_id,quantity,service_id FROM stock_movements WHERE user_id=? AND request_key=? FOR UPDATE',[$u['id'],$key])->fetch();if($existing){if($existing['product_id']!=$id || $existing['quantity']!=$qty || $existing['service_id']!=$sid)fail('This submission key was already used for different parts.',409);db()->commit();json_out(['ok'=>true,'duplicate'=>true]);}if($p['stock']+$qty<0) fail('Insufficient stock. No items were deducted.');if($p['stock']+$qty>2147483647)fail('The stock balance would exceed the supported limit.');
        query('UPDATE products SET stock=stock+? WHERE id=?',[$qty,$id]);
        query('INSERT INTO stock_movements(product_id,user_id,service_id,quantity,reason,request_key) VALUES(?,?,?,?,?,?)',[$id,$u['id'],$sid,$qty,$reason,$key]);
        if($p['stock']+$qty<=$p['reorder_level'])notify_office('inventory','Low stock: '.$p['name'],($p['stock']+$qty).' units remaining.','forecast','low-stock:'.$id.':'.date('Y-m-d'));
        audit('Stock movement',"Product $id: $qty");db()->commit();json_out(['ok'=>true]);
    case 'movements':
        require_user(['admin','staff']);json_out(['items'=>query('SELECT m.*,p.name product_name,u.name user_name FROM stock_movements m JOIN products p ON p.id=m.product_id JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 200')->fetchAll()]);
    default: fail('Action not found.',404);
    }
}
