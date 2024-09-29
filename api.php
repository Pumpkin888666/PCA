<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';
require './data/func/request_forwarding.php';
require './data/func/getAuth.php';

$act = $_POST['act'];
$authkey = aes256($_POST['authkey'], 'decrypt');
$app_version = $_POST['app_version'];
$version_id = $_POST['version_id'];
$PCASDK_VERSION = $_POST['PCASDK_VERSION'];
$sign = $_POST['sign'];

$auth = getAuth($authkey);
$app_id = $auth['app']['identification'];
$app_key = $auth['app']['appkey'];
$app_author = $auth['app']['host'];

// sign 验证
$true_sign = sign($app_id
    . $app_key
    . $authkey
    . $app_author
    . $app_version
    . $version_id
    . $PCASDK_VERSION);
if($sign != $true_sign){
    json(109, '签名错误');
}

// version_id 验证
$author_id = $auth['app']['host'];

$sql = "select * from appversion where appof = ? and version = ?;";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($app_id, $app_version));
if (!$stmt->rowCount()) {
    json(-1);
}
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$appversion_identification = $result['identification'];

$true_version_id = md5($app_id . $app_key . $app_version . $author_id . $authkey . $appversion_identification);

if($version_id != $true_version_id){
    json(110, '错误的版本唯一标识');
}

$sql = 'select * from user where identification = :identification';
$stmt = $pdo->prepare($sql);
$stmt->execute(array('identification' => $app_author));
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user['apiswitch'] != 1) {
    json(999, 'API FALSE');
}

if (!auth()) {
    json(106, '授权失败');
}

// API TIMES 更新
$sql = 'update user set api_connect_num = api_connect_num + 1 where identification = ?;';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($app_author));

// HEARTBEAT 更新
$sql = 'update appauth set heartbeat = ? , version = ? where id = ?;';
$stmt = $pdo->prepare($sql);
$stmt->execute(array(time(), $app_version, $auth['auth']['id']));

// API TIMES 更新2
$sql = 'update app set api_times = api_times + 1 where id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($auth['app']['id']));

switch ($act) {
    case 'auth':
        // 能到这已经是授权验证成功了 直接返回就好
        json(0);
        break;
    case 'getRole':
        json(
            0,
            'ok',
            array(
                'role' => $auth['auth']['role']
            )
        );
        break;
    case 'getNotice':
        json(
            0,
            'ok',
            array(
                'notice' => $auth['app']['notice']
            )
        );
        break;
    case 'getEndTime':
        json(
            0,
            'ok',
            array(
                'endtime' => $auth['auth']['end_time']
            )
        );
        break;
    case 'getUpdate':
        $sql = 'select * from appversion where appof = ? and switch = 1 order by create_time';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth['app']['identification']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $version = [];
        $n = 0;
        $i = 0;
        foreach ($result as $row) {
            if ($row['only_role'] != -1 && $row['only_role'] != $auth['auth']['role']) {
                continue;
            }
            array_push($version, $row['version']);
            if ($row['version'] == $app_version) {
                $i = $n;
            }
            $n++;
        }
        if ($version[(count($version) - 1)] == $app_version) {
            json(0, 'ok', array('update' => false));
        } else {
            json(0, 'ok', array('update' => true, 'version' => $version[(count($version) - 1)], 'next_version' => $version[($i + 1)]));
        }

        break;
    case 'getUpdatePack':
        $sql = 'select * from appversion where appof = ? and switch = 1 order by create_time';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth['app']['identification']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $version = [];
        $n = 0;
        $i = 0;
        foreach ($result as $row) {
            if($row['only_role'] == -1 || $row['only_role'] == $auth['auth']['role']){
                array_push($version, $row['version']);
            }
            if ($row['version'] == $app_version) {
                $i = $n;
            }
            $n++;
        }
        if ($version[(count($version) - 1)] == $app_version) {
            json(200, "needn't update");
        } else {
            $version = $version[($i + 1)];

            $sql = 'select * from appversion where appof = ? and switch = 1 and version = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($auth['app']['identification'], $version));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $str = 'PCA:GET_UPDATEPACK//AUTHPASS';
            $str1 = $result['update_file'] . ',' . $auth['app']['host'];
            require './data/func/filedownload_ase256_special.php';
            $str = file_aes256($str,'encrypt');
            $str1 = file_aes256($str1,'encrypt');

            // new version_id
            $sql = "select * from appversion where appof = ? and version = ?;";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($app_id, $version));
            if (!$stmt->rowCount()) {
                json(-1);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $appversion_identification = $result['identification'];
            $new_version_id = md5($app_id . $app_key . $version . $author_id . $authkey . $appversion_identification);

            json(
                0,
                'ok',
                array(
                    'url' => $str,
                    'url1' => $str1,
                    'new_version_id' => $new_version_id
                )
            );
        }
        json(-1);
        break;
    case 'getRemote-variable':
        $name = $_POST['name'];

        $sql = 'select * from remote_variables where name = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($name, $app_author));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$stmt->rowCount() || $result['switch'] == 0){
            json(-1);
        }

        json(
            0,
            'ok',
            array(
                'content' => $result['content'],
            )
        );

        break;
    case 'request_forwarding':
        $out_link = $_POST['out_link'];
        $pd = $_POST['post_data'];

        $r = request_forwarding($out_link, $pd, $pdo);

        json(0, 'ok', array('data' => $r));

        break;
    default:
        json(404);
        break;
}

function auth()
{
    global $auth, $pdo, $app_version;
    // 未激活
    if ($auth['auth']['activation_time'] == 0) {
        json(107, '授权码没有激活请使用PCAInstaller工具进行激活并安装');
    }
    // 版本可用检测
    $sql = 'select * from appversion where appof = ? and version = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($auth['app']['identification'], $app_version));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$stmt->rowCount()) {
        json(103, '错误版本');
    }
    if ($result['switch'] != 1) {
        json(104, '版本禁用！请更新');
    }

    // 软件可用检测
    if ($auth['app']['switch'] != 1) {
        json(105, '软件被关闭');
    }

    // 已激活 过期验证
    if ($auth['auth']['switch'] != 1) {
        json(102, '授权被封禁');
    }
    if ($auth['auth']['end_time'] == -1) {
        return true; // forever
    }
    if ($auth['auth']['end_time'] < time()) {
        json(101, '授权过期');
    } else {
        return true;
    }
}

function aes256($str, $model)
{
    // API通讯特别版
    global $PCASDK_VERSION, $app_version, $authkey, $app_key, $app_id, $app_author;
    // 加密2 超级AES256加密 加密密文仅当秒可解
    if ($model == "encrypt") {
        // 加密2 超级AES256加密 加密密文仅当秒可解 仅加密
        $key = 0;
        for ($i = 0; $i < 16; $i++) {
            $key .= md5($key . time() . $authkey . $app_id . $app_key . $app_version . $app_author . $PCASDK_VERSION);
        }
        for ($i = 0; $i < 8; $i++) {
            $str = openssl_encrypt($str, 'AES-256-CBC', $key, 0, 'I very like you.');
        }
        return $str;
    } else if ($model == 'decrypt') {
        $key = 0;
        for ($i = 0; $i < 16; $i++) {
            $key .= md5($key . time());
        }
        for ($i = 0; $i < 8; $i++) {
            $str = openssl_decrypt($str, 'AES-256-CBC', $key, 0, 'I very like you.');
        }
        return $str;
    }

}

function sign($str, $check = null)
{
    // 加密1 超级MD5签名
    if (!empty($check)) {
        $str = $str['code'] . $str['msg'] . $str['data'] . $str['time'];
    }
    for ($i = 0; $i < 10; $i++) {
        $str .= md5($str);
    }
    $strlen = strlen($str);
    for ($i = 0; $i < $strlen; $i++) {
        $str .= md5($str);
    }
    $str = md5($str);
    if (!empty($check)) {
        if ($str === $check) {
            return true;
        } else {
            return false;
        }
    }
    return $str;
}

function json($code, $msg = null, $data = null)
{
    // header('Content-type: application/json'); 为了强加密 加入这个头部标识会导致换行也一起加密 造成客户端解密后无法解析json
    $str = json_encode(
        array(
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'time' => time(),
            'sign' => sign($code . $msg . $data . time()),
        )
    );
    exit(aes256($str, 'encrypt'));
}
?>