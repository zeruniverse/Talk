<?php
require_once("function/basic.php");
require_once("function/config.php");
if(!isset($_GET['code'])||$_GET['code']=='') {header('Location: ./');die();}
echoheader();
?>
<script type="text/javascript" src="js/send.js"></script>
<div class="container theme-showcase">
<div class="page-header">
        <h1>Your Link</h1>
</div>
<p><strong>
    <span class="bgyellow" id="link"><?php echo $DOMAIN_NAME.$_GET['code'];?></span>
</strong> &nbsp; &nbsp; <button class="btn btn-xs btn-info" id="cplink">Copy to clipboard</button> </p>
<p>This Link will expire in 5 days.</p>
<p><a class="btn btn-md btn-success" href="index.php#sendmes">Send another message</a></p>
</div>

<?php echofooter();?>
