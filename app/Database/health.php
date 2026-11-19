<?php
declare(strict_types=1);
function database_failure_reason(Throwable $error): string {
    if(!in_array('mysql',PDO::getAvailableDrivers(),true))return 'mysql_driver_missing';
    $code=$error instanceof PDOException?(int)($error->errorInfo[1]??0):0;
    return match($code){
        1044,1045=>'credentials_or_permissions',
        1049=>'database_not_found',
        1146=>'users_table_missing',
        2002,2003,2005=>'database_host_unreachable',
        1040,1203,1226=>'database_connection_limit',
        default=>'database_query_failed'
    };
}
function portal_database_health(): array {
    try{
        $count=(int)query('SELECT COUNT(*) FROM users')->fetchColumn();
        return ['ready'=>$count>0,'reason'=>$count>0?'ready':'no_user_accounts'];
    }catch(Throwable $error){
        // Never log PDO exception text: it can contain usernames, hostnames or SQL.
        return ['ready'=>false,'reason'=>database_failure_reason($error)];
    }
}
