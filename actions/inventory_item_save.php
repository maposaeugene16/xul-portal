<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head','Bursar','Clerk'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$id=filter_var($_POST['item_id']??null,FILTER_VALIDATE_INT);
$code=strtoupper(trim((string)($_POST['item_code']??'')));
$name=trim((string)($_POST['item_name']??''));$category=trim((string)($_POST['category']??''));$unit=trim((string)($_POST['unit']??''));
$minimum=$_POST['reorder_level']??null;$notes=trim((string)($_POST['notes']??''));$active=isset($_POST['active'])?1:0;
if($code===''||strlen($code)>40||$name===''||strlen($name)>160||$category===''||strlen($category)>80||$unit===''||strlen($unit)>30||!is_numeric($minimum)||(float)$minimum<0||(float)$minimum>999999999.999||strlen($notes)>500||($id===false&&isset($_POST['item_id']))){$_SESSION['error']='Check the stock code, item name, category, unit and reorder level.';header('Location: ../?page=inventory');exit;}
$pdo=db();
try{
    $pdo->beginTransaction();
    if($id){
        $q=$pdo->prepare('SELECT item_code,item_name,unit FROM school_inventory_items WHERE id=:id FOR UPDATE');$q->execute(['id'=>$id]);$old=$q->fetch();
        if(!$old)throw new DomainException('Stock item not found.');
        $q=$pdo->prepare('SELECT COUNT(*) FROM school_inventory_movements WHERE item_id=:id');$q->execute(['id'=>$id]);$hasHistory=(int)$q->fetchColumn()>0;
        if($hasHistory&&($old['item_code']!==$code||$old['item_name']!==$name||$old['unit']!==$unit))throw new DomainException('Item code, name and unit cannot change after movements have been recorded; this protects the stock history.');
        $q=$pdo->prepare('UPDATE school_inventory_items SET item_code=:code,item_name=:name,category=:category,unit=:unit,reorder_level=:minimum,notes=:notes,active=:active WHERE id=:id');
        $q->execute(['code'=>$code,'name'=>$name,'category'=>$category,'unit'=>$unit,'minimum'=>number_format((float)$minimum,3,'.',''),'notes'=>$notes?:null,'active'=>$active,'id'=>$id]);
        $itemId=$id;$event='inventory.item_updated';
    }else{
        $q=$pdo->prepare('INSERT INTO school_inventory_items(item_code,item_name,category,unit,reorder_level,notes,active,created_by) VALUES(:code,:name,:category,:unit,:minimum,:notes,:active,:user)');
        $q->execute(['code'=>$code,'name'=>$name,'category'=>$category,'unit'=>$unit,'minimum'=>number_format((float)$minimum,3,'.',''),'notes'=>$notes?:null,'active'=>$active,'user'=>$user['id']]);
        $itemId=(int)$pdo->lastInsertId();$event='inventory.item_created';
    }
    audit_log($event,'school_inventory_items',$itemId,['item_code'=>$code,'category'=>$category]);
    $pdo->commit();$_SESSION['notice']='Stock item saved.';
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Inventory item save failed: '.$e->getMessage());$_SESSION['error']=($e instanceof PDOException&&$e->getCode()==='23000')?'That stock code is already in use.':'Could not save the stock item.';}
header('Location: ../?page=inventory');exit;
