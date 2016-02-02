<?php
session_start();
require_once("function/sqllink.php");
if(!isset($_POST['m'])||$_POST['m']==''||!isset($_POST['p'])||$_POST['p']=='') die('0'); else
{
	$p=$_POST['p'];
	$m=$_POST['m'];
	$link=sqllink();
	$sql="SELECT * FROM `talkrecord`  WHERE `code`=? AND `passwordsha`=?";
    	$res=sqlexec($sql,array($m,$p),$link);
    	$i= $res->fetch(PDO::FETCH_ASSOC);
	if($i==FALSE) die('0');
	$sql="DELETE FROM `talkrecord`  WHERE `code`=?";
	$res=sqlexec($sql,array($m),$link);
	echo $i['message'];
}
?>
