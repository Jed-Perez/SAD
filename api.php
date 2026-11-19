<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/Auth/actions.php';
require_once __DIR__.'/app/Auth/google.php';
require_once __DIR__.'/app/Dashboard/routes.php';
require_once __DIR__.'/app/Inventory/routes.php';
require_once __DIR__.'/app/Users/routes.php';
require_once __DIR__.'/app/Services/routes.php';
require_once __DIR__.'/app/Messages/routes.php';
require_once __DIR__.'/app/Settings/routes.php';
require_once __DIR__.'/app/Planner/routes.php';
require_once __DIR__.'/app/Advanced/routes.php';
try {
    $action=$_GET['action'] ?? '';
    $method=$_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST'],true)) fail('Method not allowed.',405);
    $readActions=['session','dashboard','products','users','services','service','threads','messages','blueprints','blueprint','settings','movements','audit','user_logs','message_unread','auth_devices','notifications','notification_preferences','availability','schedule_settings','appointments','plan_workspace','quotations','quotation','job_card','forecast','support_history'];
    if ($method==='GET' && !in_array($action,$readActions,true)) fail('Use POST for this action.',405);
    $data=$method==='POST'?input():$_GET;
    if ($method==='POST') check_csrf();
    if ($action==='session') json_out(['user'=>current_user(),'csrf'=>csrf(),'expired'=>!empty($_SESSION['session_expired'])]);
    if ($action==='google_start') json_out(['url'=>google_auth_start($data)]);
    if (in_array($action,['login','register','forgot_password','reset_password'],true)) handle_guest_auth($action,$data);
    $u=require_user();
    if(in_array($action,['auth_devices','revoke_device','revoke_other_devices'],true))handle_device_action($action,$data,$u);
    $staff=in_array($u['role'],['admin','staff'],true);
    $advanced=advanced_actions();if(isset($advanced[$action]))$advanced[$action]($action,$data,$u);
    if (in_array($action,['logout','profile'],true)) handle_account_action($action,$data,$u);
    if ($action==='dashboard') handle_dashboard_action($action,$data,$u,$staff);
    if (in_array($action,['products','product_save','product_archive','product_restore','stock_move','movements'],true)) handle_inventory_action($action,$data,$u);
    if (in_array($action,['users','user_save'],true)) handle_users_action($action,$data,$u);
    if (in_array($action,['services','service_create','service','service_update'],true)) handle_services_action($action,$data,$u,$staff);
    if (in_array($action,['threads','messages','message_send','message_unread'],true)) handle_messages_action($action,$data,$u,$staff);
    if (in_array($action,['settings','settings_save','audit','user_logs'],true)) handle_settings_action($action,$data,$u,$config);
    if (in_array($action,['blueprints','blueprint','blueprint_upload','blueprint_analyze','blueprint_review','blueprint_delete'],true)) handle_planner_action($action,$data,$u,$staff);
    fail('Action not found.',404);
} catch (AppError $e) {
    try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}
    json_out(['error'=>$e->getMessage(),'csrf'=>csrf()],$e->getCode() ?: 422);
} catch (PDOException $e) {
    try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}
    error_log('Database request failed; SQLSTATE '.$e->getCode());
    json_out(['error'=>$e->getCode()==='23000'?'That email or SKU already exists, or the record is still in use.':'Database unavailable. Check MySQL and run the installer.'], $e->getCode()==='23000'?409:503);
} catch (Throwable $e) {
    try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}
    error_log('API request failed: '.get_class($e));json_out(['error'=>'The request could not be completed. Please try again.'],500);
}
