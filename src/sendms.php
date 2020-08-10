<?php
require_once("function/sqllink.php");
if(!isset($_POST['m'])||$_POST['m']==''||!isset($_POST['p'])||$_POST['p']==''||!isset($_POST['ph'])) die('0'); else
{
	$expire=date('Y-m-d',strtotime('+6 day'));
	$link=sqllink();
	$m=$_POST['m'];
    $p=hash_pbkdf2('sha3-512', $_POST['p'], $SERVER_SALT, $PBKDF2_ITERS, 30);
	$typem = (int)$_POST['typem'];
    $phint=$_POST['ph'];
    $link->beginTransaction();
	while (true){
		$code='';
		for($i=1;$i<=6;$i++)
		{
			$c=random_int(0, 35);
			if($c<10) $code=$code.$c; else $code=$code.chr($c-10+ord("a"));
		}
		$sql="SELECT COUNT(*) FROM `talkrecord`  WHERE `code`=?";
		$res=sqlexec($sql,array($code),$link);
        $num= $res->fetch(PDO::FETCH_NUM);
        $num=$num[0];
		if($num==0) break;
	}
	$sql="INSERT INTO `talkrecord` VALUES (?,?,?,?,?,?,?);";
    $res=sqlexec($sql,array($code,$m,$expire,$p,$phint,$typem),$link);
	die($code);
}
?>
