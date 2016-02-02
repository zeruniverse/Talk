<?php
require_once("function/sqllink.php");
require_once("function/basic.php");
if(!isset($_GET['f'])||$_GET['f']=='') {header('Location: ./');die();}
echoheader();
?>
<script type="text/javascript" src="aes.js"></script>
<script type="text/javascript" src="sha512.js"></script>
<script type="text/javascript" src="en.js"></script>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Get Your Message</h1>
</div>
<div id='showarea'>
<?php
	$code=addslashes($_GET['f']);
	$link=sqllink();
	$sql="SELECT `hint` FROM `talkrecord`  WHERE `code`=?";
	$res=sqlexec($sql,array($code),$link);
    $hint= $res->fetch(PDO::FETCH_ASSOC);
	if($hint==false)  echo '<p>ERROR:Your message doesn\'t exists! Maybe it has been viewed or expired.</p><br />';
	else{
        echo '<form>Please input your Passphrase:<input type="text" id="pcc" /></form> <button id="bu" class="btn btn-md btn-danger" onClick="d()">Confirm</button><br /><br />';
        if($hint['hint']!='')
        {
            echo '<span style="color:red">Passphrase Hint: '.$hint['hint'].'</span><br /><br />';
        }
        echo 'You should get the passphrase (key) from the sender.<br /><br /><span style="color:blue">Please Notice: this message can be displayed only ONCE, the message will be deleted from the server once you see it.</span>';
    }
?>
</div>
<script type="text/javascript">
function d()
{
	if($('#pcc').val()=='') {alert('No passphrase detected.');return ;}
	$('#bu').html('Please Wait...');
	$('#bu').attr('disabled','disabled');
	var key=$('#pcc').val();
	var salt1 = '<?php echo $GLOBAL_SALT_2; ?>';
	var salt2 = '<?php echo $GLOBAL_SALT_3; ?>';
	$.post('getm.php',{'p':String(CryptoJS.SHA512(String(CryptoJS.SHA512(String(CryptoJS.SHA512(key+salt1))+salt2))+salt2)),'m':'<?php echo $_GET['f'];?>'},function(msg){
	if(msg=='0')
	{
		alert('Some error happens, may be caused by wrong passphrase. This page will be refreshed.');
		window.location.reload(true);
	}else
	{
		var kc=decryptchar(msg,key,'<?php echo $GLOBAL_SALT_1; ?>');
		kc='<span style="color:red">Your message is:</span><br /><br /><br /><div id="maincontent">'+kc+'</div><br /><br /><span style="color:red">This message has been deleted from the server.</span><br /><br /><p><a class="btn btn-md btn-success" href="index.php#sendmes">Send my message</a></p>';
		$('#showarea').html(kc);
	}
	});
}
</script>
</div>
<?php echofooter();?>
