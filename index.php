<?php
require_once("function/basic.php");
if(isset($_GET['f'])&&$_GET['f']!='') {header('Location: get.php?f='.$_GET['f']);die();}
echoheader();
?>
<script type="text/javascript" src="aes.js"></script>
<script type="text/javascript" src="sha512.js"></script>
<script type="text/javascript" src="en.js"></script>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Talk</h1>
</div>
	<div class="jumbotron">
		<p>This is a project for safe communications under monitored environment. All you need is a passphrase (key) that known by both you and your partner (i.e. anything both you knows but others don't know).</p>
		<p>No one knows what you're talking even they get all data you transmit in the Internet because all encryption and decryption happens on your computer, not the internet. If you are interested, see details on the bottom of this page.</p> 
</div>
<div  id="sendmes" name="sendmes" class="page-header">
        <h1>Start Your Secure Communication</h1>
</div>
<form>
Message to Send:<br /> <textarea id="m" name="m" style="width:100%; height:150px;"></textarea><br /><br />
Passphrase: <input type="text" id='p' /><br /><br />
Passphrase Hint (Alternative): <input type="text" id='phint' /><br />
(Give some hint about the passphrase to the receiver. It will be stored and displayed in plaintext form)
<br /><br />
</form>
<button class="btn btn-md btn-primary" onClick="sendm()" id="bu">Generate Message Link</button><br />
<div class="page-header">
        <h1>Know How You are Protected</h1>
</div>
<div class="jumbotron">	
		<p>When you input a message and the key (passphrase), your browser encrypt the message using the key and generate a signature of the key (Your key can't be calculated out by the signature). Then, your browser send the signature and the encrypted message to the server and the server gives back a unique link. You can send the link to your partner using unsafe channel and your partner input the link and key into his browser. His browser generates a signature of the key he inputed and send the signature to the server. The server compares the signature and stored signature. If they're same, the server returns the encrypted message and delete this record. Your partner's browser decrypt the message by the key he inputed.</p>
		<p>So as you can see, no one in the middle (including my server) can know what you're talking unless they know the key. The key is only known by you, your partner and your browser, which will never transmitted in the Internet.</p>
		<p>To make it even secure, every message is allowed to be seen exactly once. However, if your message is intercepted by someone else, it doesn't mean he got what you talked. Without the key, he only got some meaningless random codes. But please note, you are unsafe if your key is leak (e.g. someone have access to your web browser and get your key)</p>
</div>
<script type="text/javascript">
function replc(strmsg)
{
	strmsg=strmsg.split("\r").join("");
	strmsg=strmsg.split('&').join('&amp;');
	strmsg=strmsg.split('<').join('&lt;');
	strmsg=strmsg.split('>').join('&gt;');
	strmsg=strmsg.split(' ').join('&nbsp;');
	strmsg=strmsg.split("\n").join('<br />');
	return strmsg;
}
function sendm()
{
	if($('#p').val()=='' || $('#m').val()=='') {alert('No passphrase or message detected.');return ;}
	$('#bu').html('Please Wait...');
	$('#bu').attr('disabled','disabled');
	var strmsg=$('#m').val();
	var key=$('#p').val();
    var ph=$('#phint').val();
	strmsg=replc(strmsg);
	var salt1 = '<?php echo $GLOBAL_SALT_2; ?>';
	var salt2 = '<?php echo $GLOBAL_SALT_3; ?>';
	$.post('sendms.php',{'p':String(CryptoJS.SHA512(String(CryptoJS.SHA512(String(CryptoJS.SHA512(key+salt1))+salt2))+salt2)),'m':encryptchar(strmsg,key,'<?php echo $GLOBAL_SALT_1;?>'),'ph':ph},function(msg){
	if(msg!='0') window.location.href="./send.php?code="+msg; else
	{
		alert('Oops, error happens! Please retry!');
		$('#bu').html('Generate Message Link');
		$('#bu').removeAttr('disabled');
	}
	});
}
</script>
</div>
<?php echofooter();?>
