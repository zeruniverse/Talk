<?php
require_once("function/sqllink.php");
if(!isset($_POST['m'])||$_POST['m']==''||!isset($_POST['p'])||$_POST['p']=='') die('0'); else
{
	$p=hash_pbkdf2('sha3-512', $_POST['p'], $SERVER_SALT, $PBKDF2_ITERS, 30);
	$m=$_POST['m'];
	$link=sqllink();

    // delete expire
    if(!$link) die('0');
    $e=date('Y-m-d');
    $sql = "DELETE FROM `talkrecord` WHERE ? >= `expire`";
    $res=sqlexec($sql,array($e),$link);

	$sql="SELECT * FROM `talkrecord`  WHERE `code`=? AND `passwordsha`=?";
    $res=sqlexec($sql,array($m,$p),$link);
    $i= $res->fetch(PDO::FETCH_ASSOC);

    if($i==FALSE) die('0');

    if((int)$i['type']==1) die($i['message']);

	$sql="DELETE FROM `talkrecord`  WHERE `code`=?";
	$res=sqlexec($sql,array($m),$link);
	echo $i['message'];
}
?>
