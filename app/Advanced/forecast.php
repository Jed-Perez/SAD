<?php
declare(strict_types=1);
function handle_forecast(string $action,array $data,array $u): never {
    require_user(['admin','staff']);
    if($action==='forecast_settings_save'){
        $id=integer_field($data,'product_id',1);if(!query('SELECT id FROM products WHERE id=?',[$id])->fetch())fail('Product not found.',404);
        query('INSERT INTO inventory_forecast_settings(product_id,lead_days) VALUES(?,?) ON DUPLICATE KEY UPDATE lead_days=VALUES(lead_days)',[$id,integer_field($data,'lead_days',0,365)]);audit('Updated supplier lead time',(string)$id);json_out(['ok'=>true]);
    }
    $products=query('SELECT p.*,f.lead_days FROM products p LEFT JOIN inventory_forecast_settings f ON f.product_id=p.id WHERE p.active=1 ORDER BY p.name')->fetchAll();
    $usage=query('SELECT product_id,SUM(-quantity) consumed,COUNT(DISTINCT DATE(created_at)) days,MIN(created_at) first_use FROM stock_movements WHERE quantity<0 AND created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) GROUP BY product_id')->fetchAll(PDO::FETCH_UNIQUE);
    $requirements=query("SELECT i.product_id,q.service_id,SUM(i.quantity) quantity FROM quotations q JOIN quotation_versions v ON v.quotation_id=q.id AND v.version=q.current_version JOIN quotation_items i ON i.version_id=v.id JOIN services s ON s.id=q.service_id WHERE q.status='Approved' AND s.status IN ('Approved','Scheduled','In Progress') GROUP BY i.product_id,q.service_id")->fetchAll();$reserved=[];
    foreach($requirements as $r){$used=(int)query('SELECT COALESCE(SUM(-quantity),0) FROM stock_movements WHERE product_id=? AND service_id=? AND quantity<0',[$r['product_id'],$r['service_id']])->fetchColumn();$reserved[$r['product_id']]=($reserved[$r['product_id']]??0)+max(0,(int)$r['quantity']-$used);}
    foreach($products as &$p){$h=$usage[$p['id']]??null;$elapsed=$h?max(1,(int)ceil((time()-strtotime($h['first_use']))/86400)):0;$enough=$h && (int)$h['days']>=3 && $elapsed>=14;
        $p['consumed']=(int)($h['consumed']??0);$p['confirmed_requirements']=$reserved[$p['id']]??0;$p['daily_usage']=$enough?round($p['consumed']/$elapsed,3):null;
        $available=max(0,(int)$p['stock']-$p['confirmed_requirements']);$p['depletion_date']=$enough && $p['daily_usage']>0?date('Y-m-d',strtotime('+'.(int)floor($available/$p['daily_usage']).' days')):null;
        $p['suggested_reorder']=max(0,(int)$p['reorder_level']+$p['confirmed_requirements']+($enough && $p['lead_days']!==null?(int)ceil($p['daily_usage']*$p['lead_days']):0)-(int)$p['stock']);
        $p['forecast_note']=$enough?'90-day recorded consumption; confirmed requirements are subtracted.':'Insufficient data: at least 3 usage days spanning 14 days are needed.';
    }unset($p);
    json_out(['items'=>$products,'summary'=>['active'=>count($products),'low'=>count(array_filter($products,fn($p)=>$p['stock']<=$p['reorder_level'])),'out'=>count(array_filter($products,fn($p)=>$p['stock']==0)),'value'=>array_sum(array_map(fn($p)=>$p['price']*$p['stock'],$products))],
        'monthly'=>query("SELECT DATE_FORMAT(created_at,'%Y-%m') month,SUM(IF(quantity<0,-quantity,0)) used,SUM(IF(quantity>0,quantity,0)) received FROM stock_movements WHERE created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY month ORDER BY month")->fetchAll(),
        'value_history'=>query('SELECT * FROM inventory_snapshots ORDER BY snapshot_date DESC LIMIT 90')->fetchAll(),
        'recent_products'=>query('SELECT id,name,created_at,updated_at FROM products WHERE active=1 ORDER BY COALESCE(updated_at,created_at) DESC,id DESC LIMIT 8')->fetchAll(),
        'recent'=>query('SELECT m.*,p.name FROM stock_movements m JOIN products p ON p.id=m.product_id ORDER BY m.id DESC LIMIT 15')->fetchAll()]);
}
