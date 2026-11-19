<?php
declare(strict_types=1);
function handle_planner(string $action,array $data,array $u): never {
    global $config;
    require_user(['admin','staff','customer']);
    if($action==='blueprint_upload'){
        $file=$_FILES['blueprint'] ?? null;
        if(!$file || $file['error']!==UPLOAD_ERR_OK)fail('Choose a PNG, JPG, or WebP image smaller than 2 MB.');
        if($file['size']>2*1024*1024 || !$file['size'])fail('Blueprint must be smaller than 2 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        $dimensions=@getimagesize($file['tmp_name']);
        if(!isset($ext[$mime]) || !$dimensions || $dimensions[0]*$dimensions[1]>36000000)fail('Upload a valid PNG, JPG, or WebP floor-plan image up to 36 megapixels.');
        $title=strval_field($data,'title',160);$notes=strval_field($data,'notes',3000,false);$budget=number_field($data,'budget');
        if(query('SELECT COUNT(*) FROM blueprints WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)',[$u['id']])->fetchColumn()>=20)fail('Daily blueprint upload limit reached.',429);
        $filename=bin2hex(random_bytes(24)).'.'.$ext[$mime];$path=dirname(__DIR__,2).'/storage/blueprints/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$path))fail('The blueprint could not be saved.',500);
        try { query('INSERT INTO blueprints(user_id,title,filename,mime,budget,notes) VALUES(?,?,?,?,?,?)',[$u['id'],$title,$filename,$mime,$budget,$notes]); } catch(Throwable $e){unlink($path);throw $e;}
        json_out(['id'=>(int)db()->lastInsertId()]);
    }
    $id=integer_field($data,'id',1);$b=accessible_blueprint($id,$u);
    if($action==='blueprint_review'){
        require_user(['admin','staff']);if(!in_array($b['status'],['Analyzed','Edited'],true))fail('Analyze this blueprint first.');
        require_once dirname(__DIR__).'/Advanced/plans.php';$version=(int)query('SELECT COALESCE(MAX(version),0) FROM blueprint_versions WHERE blueprint_id=?',[$id])->fetchColumn();handle_plans('plan_review',['id'=>$id,'version'=>$version,'decision'=>'Revision Required','feedback'=>strval_field($data,'review_notes',3000),'internal_notes'=>''],$u);
    }
    $requested=strval_field($data,'provider',20,false) ?: default_ai_provider();
    $providers=choose_plan_providers($requested);
    if(($data['consent']??'')!=='yes')fail('Confirm that this blueprint may be processed by the selected AI provider.');
    if(query("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action='AI analysis requested' AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)",[$u['id']])->fetchColumn()>=10)fail('Hourly AI analysis limit reached. Try again later.',429);
    $leaseSeconds=max(300,ollama_timeout_seconds()+60);
    if(!query("UPDATE blueprints SET status='Analyzing',analysis_started_at=NOW() WHERE id=? AND (status<>'Analyzing' OR analysis_started_at<DATE_SUB(NOW(),INTERVAL ? SECOND))",[$id,$leaseSeconds])->rowCount())fail('Analysis is already running. Please wait.',409);
    audit('AI analysis requested',"Blueprint $id; choice $requested");session_write_close();set_time_limit(in_array('ollama',$providers,true)?ollama_timeout_seconds()+30:180);
    try{
        $plan=analyze_image($b,$providers);$estimate=build_estimate($plan,settings(),query('SELECT * FROM products WHERE active=1')->fetchAll());
        require_once dirname(__DIR__).'/Advanced/plans.php';db()->beginTransaction();$locked=query('SELECT * FROM blueprints WHERE id=? FOR UPDATE',[$id])->fetch();
        if($locked['analysis'] && !query('SELECT id FROM blueprint_versions WHERE blueprint_id=? LIMIT 1',[$id])->fetch())snapshot_plan($locked,(int)$locked['user_id'],json_decode($locked['analysis'],true),json_decode($locked['estimate'],true)??[]);
        $version=snapshot_plan($locked,(int)$u['id'],$plan,$estimate);query("UPDATE blueprints SET status='Analyzed',analysis=?,estimate=?,reviewed_at=NULL,review_notes=NULL WHERE id=?",[json_encode($plan),json_encode($estimate),$id]);
        notify_user((int)$locked['user_id'],'blueprint','Blueprint analysis ready','Review and edit the preliminary camera plan.','planner/'.$id,'plan-analysis:'.$id.':'.$version);db()->commit();
        json_out(['ok'=>true]);
    }catch(Throwable $e){query("UPDATE blueprints SET status=? WHERE id=?",[$b['analysis']?'Analyzed':'Failed',$id]);throw $e;}
}
