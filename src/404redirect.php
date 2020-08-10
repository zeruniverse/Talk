<?php
$code = str_replace(['/', '\\', '.', ' '], "", $_SERVER['REQUEST_URI']);
header("Location: /get.php?f=".$code);
exit();
