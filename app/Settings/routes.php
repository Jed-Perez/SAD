<?php
declare(strict_types=1);
function handle_settings_action(string $action,array $data,array $u,array $config): never {
    switch ($action) {
    case 'settings':
        require_user(['admin','staff']);json_out(['settings'=>settings(),'ai_ready'=>(bool)configured_ai_providers(),'ai_providers'=>configured_ai_providers(),'ai_default_provider'=>default_ai_provider(),'ai_models'=>['ollama'=>$config['ollama_model'],'openai'=>$config['openai_model'],'gemini'=>$config['gemini_model'],'meta'=>$config['meta_model']]]);
    case 'settings_save':
        require_user(['admin']);$values=[];
        foreach(['labor_per_camera','setup_labor','cable_meters_per_camera','contingency_percent'] as $key)$values[$key]=(string)number_field($data,$key,0,$key==='contingency_percent'?50:100000);
        $values['price_note']=strval_field($data,'price_note',1000);
        foreach(['duration_Installation','duration_Repair','duration_Maintenance'] as $key)if(array_key_exists($key,$data))$values[$key]=(string)integer_field($data,$key,30,480);
        foreach(['quote_tax_percent','quote_max_discount_percent'] as $key)if(array_key_exists($key,$data))$values[$key]=(string)number_field($data,$key,0,100);
        foreach(['Installation','Repair','Maintenance'] as $type){$key='checklist_'.$type;if(!array_key_exists($key,$data))continue;$text=strval_field($data,$key,10000);$items=array_values(array_filter(array_map('trim',preg_split('/\R/u',$text))));if(!$items||count($items)>60)fail('Use between one and sixty checklist tasks.');foreach($items as $label)if(mb_strlen($label)>255)fail('Checklist tasks must be under 256 characters.');$values[$key]=json_encode($items);}
        db()->beginTransaction();foreach($values as $key=>$value)query('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);audit('Updated quotation rates');db()->commit();json_out(['ok'=>true]);
    case 'audit':
    case 'user_logs':
        require_user(['admin']);
        $page=max(1,min(100000,(int)($data['page'] ?? 1)));
        $search=trim(strval_field($data,'search',120,false));
        $where=$action==='user_logs' ? " WHERE a.action IN ('Signed in','Signed out','Registered account','Failed sign in','Updated profile','Changed password','Password reset','Revoked session','Revoked other sessions')" : ' WHERE 1=1';
        $params=[];
        if($search!==''){
            $where.=' AND (u.name LIKE ? OR u.email LIKE ? OR a.action LIKE ? OR a.details LIKE ?)';
            $params=array_fill(0,4,'%'.$search.'%');
        }
        $from=' FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id';
        // Aggregate before pagination so each account appears only once.
        if(($data['view'] ?? '')==='users'){
            $total=(int)query('SELECT COUNT(*) FROM (SELECT a.user_id'.$from.$where.' GROUP BY a.user_id) log_users',$params)->fetchColumn();
            $page=min($page,max(1,(int)ceil($total/50)));$offset=($page-1)*50;
            $groups=query('SELECT a.user_id,u.name,u.email,u.role,u.active,COUNT(*) record_count,MAX(a.created_at) last_activity'.$from.$where.' GROUP BY a.user_id,u.name,u.email,u.role,u.active ORDER BY last_activity DESC,a.user_id DESC LIMIT 50 OFFSET '.$offset,$params)->fetchAll();
            json_out(['groups'=>$groups,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/50))]);
        }
        $account=null;
        if(isset($data['user_id'])){
            if($data['user_id']==='unassigned'){
                $where.=' AND a.user_id IS NULL';
                $account=['id'=>null,'name'=>'System / unknown account','email'=>'','role'=>'','active'=>null];
            }else{
                $accountId=integer_field($data,'user_id',1);
                $account=query('SELECT id,name,email,role,active FROM users WHERE id=?',[$accountId])->fetch();
                if(!$account)fail('Account not found.',404);
                $where.=' AND a.user_id=?';$params[]=$accountId;
            }
        }
        $total=(int)query('SELECT COUNT(*)'.$from.$where,$params)->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/50)));$offset=($page-1)*50;
        json_out(['items'=>query('SELECT a.*,u.name,u.role'.$from.$where.' ORDER BY a.id DESC LIMIT 50 OFFSET '.$offset,$params)->fetchAll(),'account'=>$account,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/50))]);
    default: fail('Action not found.',404);
    }
}
