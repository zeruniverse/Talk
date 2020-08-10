<?php
require_once("function/sqllink.php");
require_once("function/basic.php");
if(!isset($_GET['f'])||$_GET['f']=='') {header('Location: ./');die();}
echoheader();
?>

<script type="text/javascript" src="js/get.js"></script>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Get Your Message</h1>
</div>
<div id='showarea'>

<?php
	$code=$_GET['f'];
	$link=sqllink();
	$sql="SELECT `hint`, `type` FROM `talkrecord`  WHERE `code`=?";
	$res=sqlexec($sql,array($code),$link);
    $result= $res->fetch(PDO::FETCH_ASSOC);
	if($result==false)  echo '<p>ERROR:Your message doesn\'t exists! Maybe it has been viewed (and it is one-time-access message) or expired.</p><br /><br /><a class="btn btn-md btn-success" href="index.php#sendmes">Send my message</a>';
	else{
        echo '<form>Please input your Passphrase: <input type="text" id="pcc" /></form> <button id="bu" class="btn btn-md btn-danger" postcode="'.$code.'">Confirm</button><br /><br />';
        if($result['hint']!='')
        {
            echo '<span class="colorred">Passphrase Hint: '.$result['hint'].'</span><br /><br />';
        }
        echo 'You should get the passphrase (key) from the sender.<br />';
        if($result['type'] == 0) echo '<br /><span class="colorred">Please Note: this message can be displayed only ONCE. The message will be deleted from the server once you see it (so you might want to take notes).</span>';
    }
?>

</div>
</div>
<?php echofooter();?>
