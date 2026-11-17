<?php
declare(strict_types=1);
function database_connection(array $config, bool $selectDatabase = true): PDO {
    $dsn="mysql:host={$config['db_host']};port={$config['db_port']};";
    if ($selectDatabase) $dsn.="dbname={$config['db_name']};";
    $dsn.='charset=utf8mb4';
    $pdo=new PDO($dsn,$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($selectDatabase) $pdo->exec("SET time_zone = '+08:00'");
    return $pdo;
}
function db(): PDO {
    global $config;
    static $pdo;
    if (!$pdo) {
        $pdo=database_connection($config);
    }
    return $pdo;
}
function query(string $sql, array $args = []): PDOStatement {
    $s = db()->prepare($sql); $s->execute($args); return $s;
}
