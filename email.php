<?php
session_start();
require_once("function/basic.php")
echoheader()
?>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Seding Results</h1>
</div>
<?php
//Implement email service below.
function send_mail($address, $unique_link, $sender_name, $additional_info)
{
	//$address -> receiptent's email address
	//$unique_link -> generated unique link
	//$sender_name -> who's the sender
	//$additional_info -> what's more the sender wanna tell the receiptent


	//return true -> mail sent successfully
	//return false -> something goes wrong
	return true;
}
if(!isset($_SESSION['c'])||!isset($_POST['e'])||!isset($_POST['n'])||$_POST['e']==''||!send_mail($_POST['e'],$_SESSION['c'],$_POST['n'],$_POST['i']))
echo '<p>Fail to send the message out. Please send the following link manually:</p><br /><p>'.$_SESSION['c'].'</p>';
else {echo 'Success';}
unset($_SESSION['c']);
?>
<p><a class="btn btn-md btn-success" href="index.php#sendmes">Send another message</a></p>
</div>
<?php echofooter();?>
