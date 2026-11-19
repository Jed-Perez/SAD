<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Advanced/routes.php';
require dirname(__DIR__).'/app/Inventory/routes.php';
if(!preg_match('/^ncpcs_test_[a-f0-9]{10}$/D',(string)getenv('DB_NAME')))exit(2);
$data=json_decode(stream_get_contents(STDIN),true);
$u=query('SELECT * FROM users WHERE id=?',[$data['user_id']])->fetch();
$_SESSION=['user_id'=>$u['id'],'password_version'=>hash('sha256',$u['password_hash']),'last_activity'=>time(),'auth_started'=>time()];
$_SESSION['device_id']=create_auth_device($u,false);
usleep(150000);
try{
    if($data['action']==='stock_move')handle_inventory_action('stock_move',$data['data'],$u);
    if($data['action']==='appointment_book')handle_scheduling('appointment_book',$data['data'],$u);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();echo json_encode(['error'=>$e instanceof AppError?$e->getMessage():'Database transaction rejected','status'=>$e instanceof AppError?($e->getCode()?:422):409]);}
