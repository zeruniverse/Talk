<?php
require_once("function/basic.php");
require_once("function/config.php");
if(!isset($_GET['code'])||$_GET['code']=='') {header('Location: ./');die();}
echoheader();
?>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Your Link</h1>
</div>
<p><strong><span style="background-color:yellow;" id="link"><?php echo $DOMAIN_NAME.$_GET['code'];?></span></strong></p>
<p>This Link is Only Valid ONCE and will expire in 5 days.</p>
<p><a class="btn btn-md btn-success" href="index.php#sendmes">Send another message</a></p>

<div <?php if(!$EMAIL_SUPPORT) echo 'display="none"';?> class="page-header">
        <h1>Send This Link to Email</h1>
</div>
<form action="email.php" method="post">
Email: <input id="e" name="e" type="email" width="50%" placeholder="Input Receiver's Email"/><br /><br />
Input your name: <input id="n" name="n" type="text" width="50%" placeholder="Your name will be shown on this email"/><br /><br />
Input additional information you want to send: <input id="i" name="i" type="text" width="50%" /><br /><br />
<input class="btn btn-md btn-primary" type="submit" value="Send This Link" />
</form>
</div>
<?php echofooter();?>
