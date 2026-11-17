<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/app/Database/connection.php';
require __DIR__.'/app/Database/migrations.php';
$config=require __DIR__.'/config.php';
migrate_database(database_connection($config));
echo "Database updated. Existing products, stock, and accounts preserved.\n";
