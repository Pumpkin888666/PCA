<?php
header('Content-Disposition: attachment; filename="PCAInstaller.zip"');
$file = file_get_contents('./PCAInstaller.zip');
file_put_contents('php://output', $file);
?>