<?php
session_start();
require_once("function/sqllink.php");
require_once("function/deleteexpire.php");
if(!isset($_POST['m'])||$_POST['m']==''||!isset($_POST['p'])||$_POST['p']==''||!isset($_POST['ph'])) die('0'); else
{
	$expire=date('Y-m-d',strtotime('+6 day'));
	$link=sqllink();
	$m=$_POST['m'];
	$p=$_POST['p'];
	$phint=$_POST['ph'];
	deleteexpire($link);
    $link->beginTransaction();
	while (true){
		$code='';
		for($i=1;$i<=6;$i++)
		{
			$c=rand(0,35);
			if($c<10) $code=$code.$c; else $code=$code.chr($c-10+ord("a"));
		}
		$sql="SELECT COUNT(*) FROM `talkrecord`  WHERE `code`=?";
		$res=sqlexec($sql,array($code),$link);
        $num= $res->fetch(PDO::FETCH_NUM);
        $num=$num[0];
		if($num==0) break;
	}
	$sql="SELECT max(`id`) FROM `talkrecord`";
	$res=sqlquery($sql,$link);
    $num= $res->fetch(PDO::FETCH_NUM);
    if($num==FALSE) $id=0; else $id=$num[0];
	$id=$id+1;
	$sql="INSERT INTO `talkrecord` VALUES (?,?,?,?,?,?);";
    $res=sqlexec($sql,array($id,$m,$expire,$code,$p,$phint),$link);
	$_SESSION['c']=$DOMAIN_NAME.$code;
	die($code);
}
?>
