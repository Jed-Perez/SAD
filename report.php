<?php
require __DIR__.'/app/bootstrap.php';
try {
    $u=require_user(['admin','staff']);$type=$_GET['type']??'inventory';
    $reports=[
      'inventory'=>['Inventory report','SELECT sku,name,category,price,stock,reorder_level,ROUND(price*stock,2) stock_value FROM products WHERE active=1 ORDER BY category,name'],
      'low-stock'=>['Low-stock report','SELECT sku,name,category,stock,reorder_level FROM products WHERE active=1 AND stock<=reorder_level ORDER BY stock'],
      'services'=>['Service history',"SELECT s.id,s.title,c.name customer,t.name technician,s.type,s.status,s.scheduled_at,s.duration_minutes,s.warranty_until,s.notes,s.created_at FROM services s JOIN users c ON c.id=s.customer_id LEFT JOIN users t ON t.id=s.technician_id ORDER BY s.created_at DESC"],
      'schedule'=>['Technician schedule',"SELECT s.id,t.name technician,c.name customer,s.title,s.address,s.scheduled_at,s.duration_minutes,s.status FROM services s JOIN users c ON c.id=s.customer_id LEFT JOIN users t ON t.id=s.technician_id WHERE s.scheduled_at IS NOT NULL ORDER BY s.scheduled_at"],
      'movements'=>['Stock movements','SELECT m.id,p.sku,p.name,m.quantity,m.reason,m.service_id,u.name recorded_by,m.created_at FROM stock_movements m JOIN products p ON p.id=m.product_id JOIN users u ON u.id=m.user_id ORDER BY m.id DESC'],
    ];
    if(!isset($reports[$type]))fail('Report not found.',404);
    [$title,$sql]=$reports[$type];$statement=query($sql);$rows=$statement->fetchAll();
    $columns=[];for($i=0;$i<$statement->columnCount();$i++)$columns[]=$statement->getColumnMeta($i)['name'];
    if(($_GET['format']??'')==='csv'){
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="ncpcs-'.$type.'-'.date('Y-m-d').'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$columns);
        foreach($rows as $row){foreach($row as &$cell){if(is_string($cell)&&preg_match('/^[\s]*[=+@\-]/u',$cell))$cell="'".$cell;}unset($cell);fputcsv($out,$row);}fclose($out);exit;
    }
    function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
}catch(AppError $e){http_response_code($e->getCode() ?: 422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');exit;}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=h($title)?> · TechTrack</title><link href="assets/vendor/bootstrap.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head><body><main class="p-4"><div class="page-heading"><div><div class="eyebrow">NCPCS · TECHTRACK</div><h1><?=h($title)?></h1><p>Generated <?=date('F j, Y · g:i A')?> (Philippine time) · <?=count($rows)?> records · Amounts in PHP</p></div><button class="btn btn-primary" id="print-report">Print / Save as PDF</button></div><div class="panel table-responsive"><table class="table"><thead><tr><?php foreach($columns as $c):?><th><?=h(str_replace('_',' ',$c))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $r):?><tr><?php foreach($r as $v):?><td><?=h($v ?? '—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table><?php if(!$rows):?><p class="p-4 muted">No records to display.</p><?php endif;?></div><p class="form-text mt-3">Nasugbu Computer Parts and CCTV Services · Inventory and Service Management System</p></main><script src="assets/report.js"></script></body></html>
