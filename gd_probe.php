<?php
header('Content-Type: text/plain');
echo 'gd=' . (extension_loaded('gd') ? 'yes' : 'no') . ' imagick=' . (extension_loaded('imagick') ? 'yes' : 'no') . ' maxup=' . ini_get('upload_max_filesize') . ' post=' . ini_get('post_max_size') . "\n";
