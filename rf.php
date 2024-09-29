<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';
require './data/func/request_forwarding.php';

$ol = $_GET['ol'];

echo request_forwarding($ol, $_POST, $pdo);
exit;




?>