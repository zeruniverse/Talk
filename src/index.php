<?php
require_once("function/basic.php");
echoheader();
?>
<script type="text/javascript" src="js/index.js"></script>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Talk</h1>
</div>
	<div class="jumbotron">
		<p>This is a project for safe communications under monitored environment. All you need is a passphrase (key) known by both you and your recipient (i.e. anything both of you knows but others don't know).</p>
		<p>No one knows what you're talking even if they get access to your router / your server / your database, because all encryption and decryption happens on your web browser. We do 1M iterations of PBKDF2 - SHA512 of your key, and then, the result as key for AES256-CBC. This is the highest security standard in the industry.</p>
</div>
<div  id="sendmes" name="sendmes" class="page-header">
        <h1>Start Your Secure Communication</h1>
</div>
<form>
Message to Send:<br /> <textarea id="m" name="m" style=""></textarea><br /><br />
Passphrase: <input type="text" id='p' /><br /><br />
Passphrase Hint (Alternative): <input type="text" id='phint' /><br />
(Give some hint about the passphrase to the receiver. It will be stored and displayed in plaintext form)
<br /><br />
</form>
<button class="btn btn-md btn-primary bu" id="bu">Generate one-time-access Message Link</button>&nbsp;<button class="btn btn-md btn-success bu" id="bu1">Generate unlimited-access Message Link</button><br />
</div>
<?php echofooter();?>
