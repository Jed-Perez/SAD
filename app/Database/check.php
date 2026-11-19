<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/health.php';
$health=portal_database_health();
$advice=[
    'ready'=>'The configured database is reachable and the users table has accounts.',
    'no_user_accounts'=>'The users table exists but contains no accounts. Import the complete existing database, including its user records.',
    'mysql_driver_missing'=>'Enable the pdo_mysql PHP extension for the PHP version running this website.',
    'credentials_or_permissions'=>'Check the full database username and password, and ensure this user is assigned to the selected database with the required permissions.',
    'database_not_found'=>'The configured database does not exist on this server. Check the complete hosting database name, including its prefix.',
    'users_table_missing'=>'The connection works, but the users table is missing. Select the correct database and import the complete TechTrack export.',
    'database_host_unreachable'=>'Check the database hostname. Hostinger web hosting uses localhost. Also check whether the database service is available.',
    'database_connection_limit'=>'The hosting account has reached a database connection limit. Wait briefly or contact hosting support.',
    'database_query_failed'=>'The database readiness query failed. Ask hosting support to inspect the private server logs; do not publish credentials.'
];
echo 'TechTrack database check: '.$health['reason'].PHP_EOL.$advice[$health['reason']].PHP_EOL;
echo 'Local configuration override: '.(is_file(dirname(__DIR__,2).'/config.local.php')?'present':'absent').PHP_EOL;
$overrides=[];foreach(['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $name)if(getenv($name)!==false)$overrides[]=$name;
echo 'Environment overrides: '.($overrides?implode(', ',$overrides):'none').PHP_EOL;
echo "Credential values are intentionally omitted.\n";
exit($health['ready']?0:1);
