<?php
declare(strict_types=1);
function handle_dashboard_action(string $action,array $data,array $u,bool $staff): never {
    switch ($action) {
    case 'dashboard':
        $where='';$args=[];
        if($u['role']==='customer'){$where=' WHERE customer_id=?';$args=[$u['id']];}
        if($u['role']==='technician'){$where=' WHERE technician_id=?';$args=[$u['id']];}
        $counts=query('SELECT status,COUNT(*) count FROM services'.$where.' GROUP BY status',$args)->fetchAll();
        $latest=query('SELECT s.*,c.name customer_name,t.name technician_name FROM services s JOIN users c ON c.id=s.customer_id LEFT JOIN users t ON t.id=s.technician_id'.str_replace(['customer_id','technician_id'],['s.customer_id','s.technician_id'],$where).' ORDER BY s.created_at DESC LIMIT 6',$args)->fetchAll();
        json_out(['counts'=>$counts,'latest'=>$latest,'low_stock'=>$staff?query('SELECT * FROM products WHERE active=1 AND stock<=reorder_level ORDER BY stock LIMIT 8')->fetchAll():[], 'products'=>$staff?(int)query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn():null,'customers'=>$staff?(int)query("SELECT COUNT(*) FROM users WHERE role='customer' AND active=1")->fetchColumn():null,'ai_ready'=>(bool)configured_ai_providers()]);
    default: fail('Action not found.',404);
    }
}
