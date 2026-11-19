<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/Planner/estimate.php';
function edited_plan(array $data): array {
    $cameras=$data['cameras']??null;if(!is_array($cameras)||count($cameras)>8)fail('Use no more than eight cameras per plan.');
    $result=[];foreach($cameras as $c){if(!is_array($c))fail('Invalid camera.');$type=strval_field($c,'type',20);if(!in_array($type,['indoor','outdoor'],true))fail('Choose a supported camera type.');
        $result[]=['label'=>strval_field($c,'label',100),'type'=>$type,'x'=>number_field($c,'x',0,100),'y'=>number_field($c,'y',0,100),'direction'=>number_field($c,'direction',0,359.99),'angle'=>number_field($c,'angle',10,180),'reason'=>strval_field($c,'reason',1000,false)];
    }return ['is_floor_plan'=>true,'summary'=>strval_field($data,'summary',2000,false),'cameras'=>$result,'warnings'=>['Approximate coverage only. Dimensions, obstructions, mounting height and lighting require technician evaluation.']];
}
function plan_estimate(array $plan): array {
    if(!$plan['cameras'])return ['currency'=>'PHP','lines'=>[],'equipment'=>0,'installation_labor'=>0,'setup_labor'=>0,'contingency'=>0,'contingency_percent'=>0,'total'=>0,'warnings'=>[],'assumptions'=>[],'price_note'=>'Preliminary estimate. Add cameras to calculate equipment and labor.','created_at'=>date(DATE_ATOM)];
    return build_estimate($plan,settings(),query('SELECT * FROM products WHERE active=1')->fetchAll());
}
function snapshot_plan(array $b,int $author,array $plan,array $estimate): int {
    $version=(int)query('SELECT COALESCE(MAX(version),0) FROM blueprint_versions WHERE blueprint_id=?',[$b['id']])->fetchColumn()+1;
    query('INSERT INTO blueprint_versions(blueprint_id,version,author_id,plan,estimate) VALUES(?,?,?,?,?)',[$b['id'],$version,$author,json_encode($plan),json_encode($estimate)]);return $version;
}
function handle_plans(string $action,array $data,array $u): never {
    require_user(['admin','staff','customer']);$id=integer_field($data,'id',1);$b=accessible_blueprint($id,$u);$staff=in_array($u['role'],['admin','staff'],true);
    if($action==='plan_workspace'){
        $versions=query('SELECT id,version,author_id,created_at FROM blueprint_versions WHERE blueprint_id=? ORDER BY version DESC',[$id])->fetchAll();
        $version=integer_field($data,'version');$current=$versions?(int)$versions[0]['version']:0;
        $v=query('SELECT * FROM blueprint_versions WHERE blueprint_id=? AND version=?',[$id,$version?:$current])->fetch();if($version && !$v)fail('Plan version not found.',404);
        $reviews=query('SELECT r.id,r.version_id,r.decision,r.feedback,r.created_at,u.name'.($staff?',r.internal_notes':'').' FROM blueprint_reviews r JOIN blueprint_versions v ON v.id=r.version_id JOIN users u ON u.id=r.author_id WHERE v.blueprint_id=? ORDER BY r.id DESC',[$id])->fetchAll();
        json_out(['version'=>$v?(int)$v['version']:0,'current_version'=>$current,'plan'=>json_decode($v['plan']??$b['analysis']??'null',true),'estimate'=>json_decode($v['estimate']??$b['estimate']??'null',true),'versions'=>$versions,'reviews'=>$reviews,'catalog'=>query("SELECT id,sku,name,price FROM products WHERE active=1 AND sku IN ('CAM-OUT-4MP','CAM-IN-4MP')")->fetchAll()]);
    }
    if($action==='plan_price'){
        $plan=edited_plan($data);$plans=[];$n=count($plan['cameras']);
        foreach(['Basic Coverage'=>max(1,(int)ceil($n/2)),'Balanced Coverage'=>max(1,(int)ceil($n*0.75)),'Expanded Coverage'=>$n] as $label=>$count){$p=$plan;$p['cameras']=array_slice($plan['cameras'],0,$count);$plans[]=['name'=>$label,'count'=>count($p['cameras']),'areas'=>array_column($p['cameras'],'label'),'estimate'=>plan_estimate($p)];}
        json_out(['estimate'=>plan_estimate($plan),'comparisons'=>$plans]);
    }
    db()->beginTransaction();$b=query('SELECT * FROM blueprints WHERE id=? FOR UPDATE',[$id])->fetch();
    if($b['status']==='Analyzing')fail('Wait for the current analysis to finish before editing.',409);
    $current=(int)query('SELECT COALESCE(MAX(version),0) FROM blueprint_versions WHERE blueprint_id=?',[$id])->fetchColumn();check_revision($data,$current,'version');
    if($action==='plan_save'){
        $plan=edited_plan($data);$estimate=plan_estimate($plan);
        // Preserve a legacy AI result before the first manual edit.
        if(!$current && $b['analysis'])snapshot_plan($b,(int)$b['user_id'],json_decode($b['analysis'],true),json_decode($b['estimate'],true)??[]);
        $version=snapshot_plan($b,(int)$u['id'],$plan,$estimate);
        query("UPDATE blueprints SET analysis=?,estimate=?,status='Edited',reviewed_at=NULL,review_notes=NULL WHERE id=?",[json_encode($plan),json_encode($estimate),$id]);
        audit('Saved blueprint version',"$id: $version");db()->commit();json_out(['version'=>$version,'estimate'=>$estimate]);
    }
    require_user(['admin','staff']);$decision=strval_field($data,'decision',30);if(!in_array($decision,['Approved','Revision Required'],true))fail('Choose approve or request revision.');
    if(!$current && $b['analysis'])$current=snapshot_plan($b,(int)$b['user_id'],json_decode($b['analysis'],true),json_decode($b['estimate'],true)??[]);
    if(!$current)fail('Save a plan before reviewing it.');
    if($decision==='Approved' && !count(json_decode($b['analysis']??'{}',true)['cameras']??[]))fail('Add at least one camera before approving a layout.');
    $vid=query('SELECT id FROM blueprint_versions WHERE blueprint_id=? AND version=?',[$id,$current])->fetchColumn();$feedback=strval_field($data,'feedback',3000);$internal=strval_field($data,'internal_notes',3000,false);
    query('INSERT INTO blueprint_reviews(version_id,author_id,decision,feedback,internal_notes) VALUES(?,?,?,?,?)',[$vid,$u['id'],$decision,$feedback,$internal]);$reviewId=db()->lastInsertId();
    query('UPDATE blueprints SET reviewed_at=NOW(),review_notes=? WHERE id=?',[$decision.': '.$feedback,$id]);notify_user((int)$b['user_id'],'blueprint','Blueprint '.$decision,$feedback,'planner/'.$id,'plan-review:'.$reviewId);audit('Reviewed blueprint version',"$id: $current $decision");db()->commit();json_out(['ok'=>true]);
}
