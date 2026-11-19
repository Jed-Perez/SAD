<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
$root=dirname(__DIR__,2);$directory=$root.'/storage/backups/'.date('Ymd-His');
if(!mkdir($directory,0700,true))throw new RuntimeException('Cannot create backup directory.');
$out=fopen($directory.'/database.sql','xb');
fwrite($out,"SET FOREIGN_KEY_CHECKS=0;\n");
db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');db()->beginTransaction();
foreach(query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid table name.');
    $ddl=query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    fwrite($out,$ddl.";\n");
    $rows=query('SELECT * FROM `'.$table.'`');
    while($row=$rows->fetch(PDO::FETCH_NUM))fwrite($out,'INSERT INTO `'.$table.'` VALUES('.implode(',',array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),$row)).");\n");
}
db()->commit();fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($out);
$zip=new ZipArchive();$zip->open($directory.'/source.zip',ZipArchive::CREATE|ZipArchive::EXCL);
$filter=new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),fn($file)=>!in_array($file->getFilename(),['.git','.tools','storage','.artifacts'],true));
$iterator=new RecursiveIteratorIterator($filter);
foreach($iterator as $file){$relative=substr($file->getPathname(),strlen($root)+1);if(preg_match('#^(\.git|\.tools|storage|tests[\\\\/]\.artifacts)([\\\\/]|$)#',$relative))continue;if($file->isFile())$zip->addFile($file->getPathname(),$relative);}
$zip->close();echo "Private database and source backup created.\n";
