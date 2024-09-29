<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';
require './data/func/filedownload_ase256_special.php';

// 文件获取
$url = file_aes256($_POST['url'],'decrypt');
$url1 = file_aes256($_POST['url1'], 'decrypt');
$file_type = $_POST['file_type'];


switch ($file_type){
    case 'update_file':
        if ($url != 'PCA:GET_UPDATEPACK//AUTHPASS') {
            exit;
        }

        $i = explode(',', $url1);
        $sql = 'select * from upload_file where filename = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($i[0], $i[1]));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Disposition: attachment; filename="versionupdate.zip"');
        $file = file_get_contents('./uploads/' . $result['save_filename']);
        file_put_contents('php://output', $file);
        break;
    case 'install_pack':
        if ($url != 'PCA:GET_INSTALLPACK//AUTHPASS') {
            exit;
        }

        $i = explode(',', $url1);
        $sql = 'select * from upload_file where filename = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($i[0], $i[1]));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Disposition: attachment; filename="versionupdate.zip"');
        $file = file_get_contents('./uploads/' . $result['save_filename']);
        file_put_contents('php://output', $file);
        break;
    default:
        echo 'What can I say?';
        break;
}


?>