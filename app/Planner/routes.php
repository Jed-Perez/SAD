<?php
declare(strict_types=1);
function handle_planner_action(string $action,array $data,array $u,bool $staff): never {
    switch ($action) {
    case 'blueprints':
        require_user(['admin','staff','customer']);
        json_out(['items'=>query('SELECT b.id,b.title,b.status,b.budget,b.created_at,b.reviewed_at,u.name customer_name FROM blueprints b JOIN users u ON u.id=b.user_id'.($staff?'':' WHERE b.user_id=?').' ORDER BY b.id DESC',$staff?[]:[$u['id']])->fetchAll(),'ai_ready'=>(bool)configured_ai_providers(),'ai_providers'=>configured_ai_providers(),'ai_auto_providers'=>automatic_ai_providers(),'ai_default_provider'=>default_ai_provider()]);
    case 'blueprint':
        $b=accessible_blueprint(integer_field($data,'id',1),$u);unset($b['filename']);$b['analysis']=$b['analysis']?json_decode($b['analysis'],true):null;$b['estimate']=$b['estimate']?json_decode($b['estimate'],true):null;json_out(['item'=>$b,'ai_ready'=>(bool)configured_ai_providers(),'ai_providers'=>configured_ai_providers(),'ai_auto_providers'=>automatic_ai_providers(),'ai_default_provider'=>default_ai_provider()]);
    case 'blueprint_upload':
    case 'blueprint_analyze':
    case 'blueprint_review':
        require_once dirname(__DIR__).'/planner.php'; handle_planner($action,$data,$u);break;
    default: fail('Action not found.',404);
    }
}
