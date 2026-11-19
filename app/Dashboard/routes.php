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
        $summary=[
            'pending_appointments'=>0,
            'pending_quotations'=>0,
            'today_jobs'=>0,
            'next_service'=>null,
            'revision_requests'=>0,
        ];
        if($staff){
            $summary['pending_appointments']=query("SELECT COUNT(*) FROM appointment_requests WHERE status='Pending'")->fetchColumn();
            $summary['pending_quotations']=query("SELECT COUNT(*) FROM quotations WHERE status IN ('Sent','Viewed','Revision Requested')")->fetchColumn();
            $summary['revision_requests']=query("SELECT COUNT(*) FROM services WHERE status='Revision Required'")->fetchColumn();
        }elseif($u['role']==='customer'){
            $summary['pending_appointments']=query("SELECT COUNT(*) FROM appointment_requests a JOIN services s ON s.id=a.service_id WHERE s.customer_id=? AND a.status='Pending'",[$u['id']])->fetchColumn();
            $summary['pending_quotations']=query("SELECT COUNT(*) FROM quotations WHERE customer_id=? AND status IN ('Sent','Viewed','Revision Requested')",[$u['id']])->fetchColumn();
            $summary['revision_requests']=query("SELECT COUNT(*) FROM services WHERE customer_id=? AND status='Revision Required'",[$u['id']])->fetchColumn();
            $summary['next_service']=query("SELECT id,title,status,scheduled_at,estimated_completion FROM services WHERE customer_id=? AND status IN ('Approved','Scheduled','In Progress') AND (scheduled_at IS NULL OR scheduled_at>=NOW()) ORDER BY CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END,scheduled_at ASC,id ASC LIMIT 1",[$u['id']])->fetch();
        }else{
            $today=date('Y-m-d');
            $summary['today_jobs']=query("SELECT COUNT(*) FROM services WHERE technician_id=? AND status IN ('Scheduled','In Progress') AND DATE(scheduled_at)=?",[$u['id'],$today])->fetchColumn();
            $summary['pending_appointments']=query("SELECT COUNT(*) FROM appointment_requests WHERE technician_id=? AND status='Pending'",[$u['id']])->fetchColumn();
            $summary['next_service']=query("SELECT id,title,status,scheduled_at,estimated_completion FROM services WHERE technician_id=? AND status IN ('Scheduled','In Progress') AND scheduled_at>=NOW() ORDER BY scheduled_at ASC,id ASC LIMIT 1",[$u['id']])->fetch();
        }
        foreach($summary as $key=>$value){
            if(is_numeric($value))$summary[$key]=(int)$value;
        }
        json_out(['counts'=>$counts,'latest'=>$latest,'summary'=>$summary,'low_stock'=>$staff?query('SELECT * FROM products WHERE active=1 AND stock<=reorder_level ORDER BY stock LIMIT 8')->fetchAll():[], 'products'=>$staff?(int)query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn():null,'customers'=>$staff?(int)query("SELECT COUNT(*) FROM users WHERE role='customer' AND active=1")->fetchColumn():null,'ai_ready'=>(bool)configured_ai_providers()]);
    default: fail('Action not found.',404);
    }
}
