<?php
$code = str_replace(['/', '\\', '.', ' '], "", $_SERVER['REQUEST_URI']);
header("Location: /down.php?f=".$code);
exit();
