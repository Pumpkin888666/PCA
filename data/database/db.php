<?php
$dsn = "mysql:dbname=" . $dbconfig['dbname'] . ";host=" . $dbconfig['host'] . ":" . $dbconfig['port'];
$user = $dbconfig['user'];
$password = $dbconfig['pwd'];
try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('db error:' . $e->getMessage());
}
?>