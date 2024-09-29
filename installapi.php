<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';
require './data/func/getAuth.php';

$act = $_POST['act'];
$auth_code = $_POST['auth_code'];
$auth = getAuth($auth_code);

switch ($act) {
    case 'auth':
        if ($auth['auth']['activation_time'] != 0) {
            json(108, '非法激活');
        }

        $app_version = getNewVersion();

        json(0, 'ok', array(
            'app_name' => $auth['app']['appname'],
            'app_version' => $app_version
        )
        );
        break;
    case 'activation':
        if ($auth['auth']['activation_time'] != 0) {
            json(108, '非法激活');
        }

        $activation_time = time();
        $end_time = time();
        switch ($auth['auth']['auth_time_type']) {
            case 'minute':
                $end_time += $auth['auth']['auth_time'] * 60;
                break;
            case 'hour':
                $end_time += $auth['auth']['auth_time'] * 3600;
                break;
            case 'day':
                $end_time += $auth['auth']['auth_time'] * 86400;
                break;
            case 'month':
                $end_time += $auth['auth']['auth_time'] * 2592000;
                break;
            case 'year':
                $end_time += $auth['auth']['auth_time'] * 31557600;
                break;
            case 'forever':
                $end_time = -1;
                break;
        }

        $sql = 'update appauth set activation_time = ? , end_time = ?,switch = ? where id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($activation_time, $end_time, 1, $auth['auth']['id']));
        if (!$stmt->rowCount()) {
            json(100, '授权码激活失败');
        }

        $app_id = $auth['app']['identification'];
        $app_key = $auth['app']['appkey'];
        $app_version = getNewVersion();
        $author_id = $auth['app']['host'];

        $sql = "select * from appversion where appof = ? and version = ?;";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($app_id, $app_version));
        if (!$stmt->rowCount()) {
            json(100, '授权码激活失败');
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appversion_identification = $result['identification'];

        $version_id = md5($app_id . $app_key . $app_version . $author_id . $auth_code . $appversion_identification);

        json(0, '授权码激活成功，已获得权限。', array('version_id' => $version_id));

        break;
    case 'getInstallPack':
        $app_version = getNewVersion();

        // 授权生成
        $sql = 'select * from appversion where appof = ? and switch = 1 and version = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth['app']['identification'], $app_version));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $str = 'PCA:GET_INSTALLPACK//AUTHPASS';
        $str1 = $result['install_file'] . ',' . $auth['app']['host'];
        require './data/func/filedownload_ase256_special.php';
        $str = file_aes256($str, 'encrypt');
        $str1 = file_aes256($str1, 'encrypt');

        json(
            0,
            'ok',
            array(
                'url' => $str,
                'url1' => $str1
            )
        );

        break;
    default:
        json(404);
        break;
}

function json($code, $msg = null, $data = null)
{
    header("Content-Type:application/json; charset=utf-8");
    echo json_encode(array("code" => $code, "msg" => $msg, "data" => $data));
    exit;
}

function getNewVersion()
{
    global $auth, $pdo;
    // 版本搜寻
    $sql = 'select * from appversion where appof = ? and switch = 1 order by create_time';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($auth['app']['identification']));
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $version = [];
    foreach ($result as $row) {
        if ($row['only_role'] != -1 && $row['only_role'] != $auth['auth']['role']) {
            continue;
        }
        array_push($version, $row['version']);
    }
    return end($version);
}
?>