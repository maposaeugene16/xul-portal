<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/auth.php';
$user=current_user();
if(!$user||$user['role']!=='Administrator'){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}

$pdo=db();$output=null;
try{
    $objectCheck=$pdo->query("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW' LIMIT 1")->fetchColumn();
    $triggerCheck=$pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() LIMIT 1')->fetchColumn();
    $routineCheck=$pdo->query('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() LIMIT 1')->fetchColumn();
    if($objectCheck||$triggerCheck||$routineCheck)throw new RuntimeException('This database contains views, triggers or routines. Ask a database administrator to create a complete server backup.');
    $nonTransactional=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB' LIMIT 1")->fetchColumn();
    if($nonTransactional)throw new RuntimeException('A table is not InnoDB, so a consistent application backup cannot be created. Ask a database administrator for a server backup.');
    $tables=$pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
    if(!$tables)throw new RuntimeException('The database has no tables to back up.');
    audit_log('system.database_backup_requested','database',null,['table_count'=>count($tables)]);
    $pdo->exec("SET time_zone='+00:00'");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $output=tmpfile();
    if($output===false)throw new RuntimeException('Could not create a temporary backup stream.');
    $config=require __DIR__.'/../config.php';
    $databaseName='`'.str_replace('`','``',(string)$config['database']).'`';
    $write=static function(string $text)use($output):void{$length=strlen($text);$offset=0;while($offset<$length){$written=fwrite($output,substr($text,$offset));if($written===false||$written===0)throw new RuntimeException('Could not write the temporary backup stream.');$offset+=$written;}};
    $write("-- Mavambo SchoolOS database backup\n-- Created: ".date(DATE_ATOM)."\n-- Restore only into an empty database. This file contains private learner, guardian and staff data.\n\n");
    $write("USE {$databaseName};\nSET NAMES utf8mb4;\nSET time_zone='+00:00';\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $insertColumnsByTable=[];
    foreach($tables as $tableRow){
        $table=(string)$tableRow[0];
        $quoted='`'.str_replace('`','``',$table).'`';
        $ddl=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM);
        if(!$ddl||!isset($ddl[1]))throw new RuntimeException('Could not read a table definition.');
        $write($ddl[1].";\n");
        $columns=$pdo->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
        $insertColumns=[];
        foreach($columns as $column){if(!str_contains(strtoupper((string)$column['Extra']),'GENERATED'))$insertColumns[]=(string)$column['Field'];}
        $insertColumnsByTable[$table]=$insertColumns;
        $write("\n");
    }
    $write("START TRANSACTION;\n");
    foreach($tables as $tableRow){
        $table=(string)$tableRow[0];
        $quoted='`'.str_replace('`','``',$table).'`';
        $insertColumns=$insertColumnsByTable[$table];
        if(!$insertColumns)continue;
        $columnSql=implode(',',array_map(static fn(string $c):string=>'`'.str_replace('`','``',$c).'`',$insertColumns));
        $select=$pdo->query('SELECT '.$columnSql.' FROM '.$quoted);
        while($row=$select->fetch(PDO::FETCH_NUM)){
            $values=[];
            foreach($row as $value)$values[]=$value===null?'NULL':$pdo->quote((string)$value);
            $write('INSERT INTO '.$quoted.' ('.$columnSql.') VALUES ('.implode(',',$values).');'."\n");
        }
        $select->closeCursor();
        $write("\n");
    }
    $write("COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\nSET time_zone=DEFAULT;\n");
    $pdo->commit();
    rewind($output);
    while(ob_get_level()>0)ob_end_clean();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="mavambo-backup-'.date('Y-m-d-His').'.sql"');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    fpassthru($output);
    fclose($output);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    if(is_resource($output))fclose($output);
    error_log('Database backup failed: '.$e->getMessage());
    if(!headers_sent()){http_response_code(500);header('Content-Type: text/plain; charset=utf-8');}
    echo 'Database backup could not be completed. Review the application error log or ask the database administrator for help.';
}
