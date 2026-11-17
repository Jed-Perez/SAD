<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$c = require __DIR__ . '/config.php';
require __DIR__ . '/app/Database/connection.php';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $c['db_name'])) die("Invalid database name.\n");
$db = database_connection($c,false);
$db->exec('CREATE DATABASE IF NOT EXISTS `' . $c['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE `' . $c['db_name'] . '`');
$db->exec(file_get_contents(__DIR__ . '/database/schema.sql'));
require __DIR__.'/app/Database/migrations.php';
migrate_database($db);
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { echo "Database already installed. Existing records preserved.\n"; exit; }
$db->beginTransaction();
$password = bin2hex(random_bytes(8)) . '!Aa';
$s = $db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
$s->execute(['NCPCS Administrator', 'admin@ncpcs.local', password_hash($password, PASSWORD_DEFAULT), 'admin']);
$products = [
 ['CAM-OUT-4MP','4MP PoE outdoor bullet camera','Outdoor camera',2450,12,4],
 ['CAM-IN-4MP','4MP PoE indoor turret camera','Indoor camera',2150,8,3],
 ['NVR-8-POE','8-channel PoE network recorder','Recorder',6800,3,2],
 ['HDD-1TB-SUR','1TB surveillance hard drive','Storage',2850,5,2],
 ['CAT6-M','Cat6 cable (per meter)','Cable',25,300,60],
 ['INSTALL-KIT','Junction box and connector kit','Accessories',250,16,5],
 ['SSD-500','500GB SATA solid state drive','Computer parts',1800,8,3],
 ['RAM-8-DDR4','8GB DDR4 desktop memory','Computer parts',1250,4,3],
];
$s = $db->prepare('INSERT INTO products(sku,name,category,price,stock,reorder_level) VALUES(?,?,?,?,?,?)');
foreach ($products as $p) {
 $s->execute($p); $id=$db->lastInsertId();
 $db->prepare('INSERT INTO stock_movements(product_id,user_id,quantity,reason) VALUES(?,1,?,?)')->execute([$id,$p[4],'Sample opening stock — replace with physical count']);
}
$defaults = ['labor_per_camera'=>'850','setup_labor'=>'1500','cable_meters_per_camera'=>'20','contingency_percent'=>'10','price_note'=>'Sample academic-project rates, not supplier quotations. Update before quoting customers.'];
$s = $db->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)');
foreach ($defaults as $k=>$v) $s->execute([$k,$v]);
$db->commit();
echo "Installed TechTrack in {$c['db_name']}.\nAdministrator: admin@ncpcs.local\nTemporary password: $password\nChange this password in Profile after signing in.\nSample inventory and prices are for demonstration; no customer records were created.\n";
