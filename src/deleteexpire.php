<?php 
require_once("../../functionzzeyu/sqllink.php");
if($_GET['key']!="zzytalk") header("Location: ./");
$link=sqllink();
if(!$link) {echo "db error";die();}
$e=date('Y-m-d');
$sql = "SELECT COUNT(`id`) FROM `talkrecord` WHERE ? >= `expire`";
$res=sqlexec($sql,array($e),$link);
$num= $res->fetch(PDO::FETCH_NUM);
$num=$num[0];
$sql = "DELETE FROM `talkrecord` WHERE ? >= `expire`";
$res=sqlexec($sql,array($e),$link);
echo 'delete'.$num.'recors!';
?>