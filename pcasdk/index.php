<?php
header('Content-Disposition: attachment; filename="PCASdk.zip"');
$file = file_get_contents('./383d017651c045c0c1d2c8813f7eaaca/PCASdk_v1point0.zip');
file_put_contents('php://output', $file);
?>