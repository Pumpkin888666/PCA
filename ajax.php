<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';

$do = isset($_POST['do']) ? $_POST['do'] : $_GET['do'];

session_start();
if (!isset($_SESSION['User']) && $do != 'login' && $do != 'register') {
    json(100, '您没登录');
}
function getUser()
{
    // 获取登录用户信息
    global $pdo;
    $sql = 'select * from user where identification = :identification';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array('identification' => $_SESSION['Identification']));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        if ($user['isblack']) {
            json(-1, '你已被拉黑');
        }
        return $user;
    }
    session_destroy();
    json(101, '用户数据异常');
}

switch ($do) {
    case 'login':
        // 登录
        if ($_SESSION['User']) {
            json(104, '您已登录');
        }
        $sql = 'select * from user where username = :username and password = :password';
        $stmt = $pdo->prepare($sql);
        $pwd = md5($_POST['password']);
        $stmt->execute(array('username' => $_POST['username'], 'password' => $pwd));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($user['isblack']) {
                json(102, '登录失败，你已被拉黑');
            }
            $_SESSION['User'] = $user['username'];
            $_SESSION['Identification'] = $user['identification'];
            json(0);
        } else {
            json(-1, '登录失败，请检查你的登录信息');
        }
        break;
    case 'loginout':
        session_destroy();
        break;
    case 'getApiSet':
        // 获取用户身份标识以及API开关
        $user = getUser();
        $sql = "select identification,apiswitch from user where id = :id;";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('id' => $user['id']));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        json(
            0,
            'ok',
            array(
                'identification' => $result['identification'],
                'apiswitch' => $result['apiswitch'] ? true : false,
            )
        );
        break;
    case 'setApiSet':
        // API 设置
        $user = getUser();
        $model = $_POST['model'];
        switch ($model) {
            case 'apiswitch':
                $now = $_POST['now'];
                if ($now == $user['apiswitch']) {
                    json(0, '无须操作');
                }
                $sql = 'update user set apiswitch = :apiswitch where id = :id';
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute(array('apiswitch' => $now, 'id' => $user['id']))) {
                    json(0);
                }
                json(-1, '操作失败');
                break;
        }
        json(-1, '参数错误');
        break;
    case 'getAppList':
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $w = ($page - 1) * $limit;
        $applist = [];
        $count = 0;
        $user = getUser();

        $sql = 'select * from app where host = :host';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('host' => $user['identification']));
        $count = $stmt->rowCount();

        $sql = "select * from app where host = ? limit ?,?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $user['identification']);
        $stmt->bindParam(2, $w, PDO::PARAM_INT); // 不写第三个参数无法查询
        $stmt->bindParam(3, $limit, PDO::PARAM_INT); // 不写第三个参数无法查询
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            array_push(
                $applist,
                array(
                    'appname' => $row['appname'],
                    'identification' => $row['identification'],
                    'appkey' => $row['appkey'],
                    'create_time' => date('Y-m-d H:i:s', $row['create_time']),
                    'switch' => $row['switch'] == 1 ? '<span style="color:green">启用</span>' : '<span style="color:red">禁用</span>'
                )
            );
        }

        json(0, 'ok', array('items' => $applist, 'count' => $count));
        break;
    case 'ajaxHtml':
        $request_of = $_GET['request_of'];
        $html = $_GET['html'];
        if ($request_of == 'admin') {
            $u = $_SESSION['User'];
            foreach ($DB->query('select isadmin from user where username = "' . $u . '";') as $row) {
                if ($row['isadmin'] != 1) {
                    echo file_get_contents('./data/ajaxHtml/error.html');
                    break;
                }
            }
        } else if ($request_of != 'user') {
            echo file_get_contents('./data/ajaxHtml/error.html');
            break;
        }
        if (empty($html)) {
            echo file_get_contents('./data/ajaxHtml/error.html');
            break;
        }
        if (file_exists('./data/ajaxHtml/' . $request_of . '/' . $html)) {
            echo file_get_contents('./data/ajaxHtml/' . $request_of . '/' . $html);
            break;
        }
        echo file_get_contents('./data/ajaxHtml/error.html');
        break;
    case 'addApp':
        $appname = $_POST['appname'];
        $notice = $_POST['notice'];
        $create_time = time();
        $ai = md5($appname . $i . $create_time . rand(0, $create_time));
        $appkey = md5($appname . $i . $create_time . rand(0, $create_time) . $ai);
        $vi = md5('1000' . $ai . time() . rand(0, time()));
        $user = getUser();

        try {
            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            $pdo->beginTransaction();

            $sql = 'select identification from app where host = ? and appname = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($user['identification'], $appname));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                throw new PDOException('用户下存在同名应用，身份标识<strong style="color:red">' . $result['identification'] . '</strong>');
            }

            $sql = 'insert into app(appname,identification,appkey,host,switch,create_time,notice) value(?,?,?,?,?,?,?)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($appname, $ai, $appkey, $user['identification'], 1, $create_time, $notice));
            if (!$stmt->rowCount()) {
                throw new PDOException('软件创建失败');
            }

            $sql = 'insert into appversion(version,appof,identification,switch,create_time) value(?,?,?,?,?)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(1000, $ai, $vi, 1, $create_time));
            if (!$stmt->rowCount()) {
                throw new PDOException('软件初始版本1000创建失败');
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            json(-1, $e->getMessage());
        }
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

        json(0);
        break;
    case 'deleteApp':
        $appidentification = $_POST['appidentification'];
        $secondPassword = $_POST['secondPassword'];
        $user = getUser();

        if (md5($secondPassword) != $user['secondpassword']) {
            json(-1, '二级密码错误');
        }

        try {
            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            $pdo->beginTransaction();

            $sql = 'delete from app where identification = ? and host = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($appidentification, $user['identification']));
            if (!$stmt->rowCount()) {
                throw new PDOException('软件删除失败');
            }

            $sql = 'delete from appversion where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($appidentification));
            if (!$stmt->rowCount()) {
                throw new PDOException('软件版本删除失败');
            }

            $sql = 'delete from appauth where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($appidentification));

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            json(-1, $e->getMessage());
        }
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

        json(0);
        break;
    case 'getAppInformation':
        $identification = $_POST['identification'];
        $user = getUser();

        $sql = 'select switch,notice from app where host = ? and identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification'], $identification));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            json(
                0,
                'ok',
                array(
                    'appswitch' => $result['switch'] == 1 ? true : false,
                    'notice' => $result['notice'],
                )
            );
        }

        json(-1, '找不到软件信息');
        break;
    case 'setAppInformation':
        $identification = $_POST['identification'];
        $AppSwitch = $_POST['AppSwitch'];
        $notice = $_POST['notice'];
        $user = getUser();
        if (strlen($notice) > 500) {
            json(-1, '软件公告不能大于500字符');
        }

        $sql = 'update app set switch = ? , notice = ? where host = ? and identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($AppSwitch, $notice, $user['identification'], $identification));
        if (!$stmt->rowCount()) {
            json(-1, '操作失败或<strong style="color:green">用户提交了相同的数据</strong>');
        }

        json(0);
        break;
    case 'AppQuickActions':
        $Actions = $_POST['Actions'];
        if (empty($Actions)) {
            json(-1, '参数缺失');
        }
        $user = getUser();
        switch ($Actions) {
            case 'openAllApp':
                $sql = 'update app set switch = 1 where host = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($user['identification']));
                if ($stmt->rowCount()) {
                    json(0);
                }
                break;
            case 'closeAllApp':
                $sql = 'update app set switch = 0 where host = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($user['identification']));
                if ($stmt->rowCount()) {
                    json(0);
                }
                break;
        }
        json(-1, '操作失败');
        break;
    case 'getAppVersion':
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $w = ($page - 1) * $limit;
        $app = [];
        $version = [];
        $count = 0;
        $user = getUser();
        $appof = $_GET['appof'];

        $sql = 'select identification,appname from app where host = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $app = $stmt->fetchAll();

        if (isset($appof) && !empty($appof)) {
            // 点击了筛选APP 即使空选了也会设置appof 只不过为空
            $sql = 'select identification,appname from app where host = ? and identification = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($user['identification'], $appof));
            // 重新赋值
            $app = $stmt->fetchAll();
        }

        foreach ($app as $row1) {
            $sql = 'select * from appversion where appof = ? order by create_time desc limit ?,?';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $row1['identification']);
            $stmt->bindParam(2, $w, PDO::PARAM_INT);
            $stmt->bindParam(3, $limit, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetchAll();
            foreach ($result as $row2) {
                array_push(
                    $version,
                    array(
                        'version' => $row2['version'],
                        'appof' => $row1['appname'],
                        'create_time' => date('Y-m-d H:i:s', $row2['create_time']),
                        'switch' => $row2['switch'] == 1 ? '<span style="color:green">可用</span>' : '<span style="color:red">禁用</span>',
                        'identification' => $row2['identification'],
                        'only_role' => $row2['only_role'] == -1 ? '全部' : $row2['only_role']
                    )
                );
            }

            $sql = 'select * from appversion where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $row1['identification']);
            $stmt->execute();
            $count += $stmt->rowCount();
        }

        json(0, 'ok', array('items' => $version, 'count' => $count));
        break;
    case 'addAppVersion':
        $version = $_POST['version'];
        $appof = $_POST['appof'];
        $identification = md5($version . $appof . time() . rand(0, time()));
        $user = getUser();

        // 查询提交的软件是不是这个用户的
        $sql = 'select host from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        // 查询有没有重复的版本
        $sql = 'select version from appversion where appof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['version'] == $version) {
            json(-1, '重复的版本');
        }

        // 添加
        $sql = 'insert into appversion(version,appof,identification,switch,create_time) value(?,?,?,?,?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($version, $appof, $identification, 0, time()));
        if ($stmt->rowCount()) {
            json(0);
        }

        json(-1);
        break;
    case 'deleteAppVersion':
        $versionidentification = $_POST['versionidentification'];
        $secondPassword = $_POST['secondPassword'];
        $user = getUser();

        if (md5($secondPassword) != $user['secondpassword']) {
            json(-1, '二级密码错误');
        }

        // 查询提交的软件是不是这个用户的 是不是初始版本
        $sql = 'select appof,version from appversion where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($versionidentification));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appof = $result['appof'];
        if ($result['version'] == 1000) {
            json(-1, '不要删除1000版本');
        }

        $sql = 'select host from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        $sql = 'delete from appversion where identification = ? and appof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($versionidentification, $appof));
        if (!$stmt->rowCount()) {
            json(-1, '版本删除失败');
        }

        json(0);
        break;
    case 'register':
        json(-1, '注册关闭！');
        $username = $_POST['username'];
        $password = $_POST['password'];
        $repassword = $_POST['repassword'];
        $email = $_POST['email'];
        if (empty($username) || empty($password) || empty($repassword) || empty($email) || $password != $repassword) {
            json(-1, '不合规数据');
        }

        $sql = 'select * from user where username = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($username));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result){
            json(-1, '重名！');
        }


        $identification = md5($username . $password . $email . time() . rand(0, time()));

        $sql = 'insert into user(username,identification,password,secondpassword,email,join_time) value(?,?,?,?,?,?);';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($username, $identification, md5($password), md5($password), $email, time()));
        if ($stmt->rowCount()) {
            json(0);
        }
        json(-1);
        break;
    case 'getFileList':
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $w = ($page - 1) * $limit;
        $filelist = [];
        $count = 0;
        $user = getUser();

        $sql = 'select * from upload_file where userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $count = $stmt->rowCount();

        $sql = "select * from upload_file where userof = ? order by upload_time desc limit ?,?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $user['identification']);
        $stmt->bindParam(2, $w, PDO::PARAM_INT); // 不写第三个参数无法查询
        $stmt->bindParam(3, $limit, PDO::PARAM_INT); // 不写第三个参数无法查询
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            if ($row['switch'] == 1) {
                $switch = '<span style="color:green">可用</span>';
            } else {
                if ($row['upload_success']) {
                    $switch = '<span style="color:red">不可用</span>';
                } else {
                    $switch = '<span style="color:red">上传未完成</span>';
                }
            }

            array_push(
                $filelist,
                array(
                    'filename' => $row['filename'],
                    'upload_time' => date('Y-m-d H:i:s', $row['upload_time']),
                    'ip' => $row['ip'],
                    'status' => $switch
                )
            );
        }

        json(0, 'ok', array('items' => $filelist, 'count' => $count));
        break;
    case 'upload_prepare':

        if (!file_exists('./uploads_temp')) {
            mkdir('./uploads_temp', 0755);
        }

        $user = getUser();
        $file_md5 = $_POST['file_md5'];
        $totalPieces = $_POST['totalPieces'];
        $filename = $_POST['filename'];

        // 检查 相同内容文件 上传中断事件
        $sql = 'select * from upload_file where md5_file = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($file_md5));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            if ($row['upload_success'] == 1 && $row['userof'] != $user['identification']) {
                // 已有别的用户上传过相同数据 直接增加数据
                $sql = "insert into upload_file(filename,save_filename,md5_file,upload_time,userof,ip,switch,upload_success) value(?,?,?,?,?,?,?,?);";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($filename, $row['save_filename'], $row['md5_file'], time(), $user['identification'], $_SERVER['REMOTE_ADDR'], 1, 1));
                if ($stmt->rowCount()) {
                    json(200);
                }
                json(201);
            }
            if ($row['userof'] == $user['identification'] && $row['upload_success'] == 0) {
                // 上传过 但是没有上传完成 返回数据前端断点续传
                json(203, 'resume', array('upload_id' => $row['temp_id'], 'ok_chunk' => (int) $row['ok_chunk']));
            }
            if ($row['userof'] == $user['identification'] && $row['upload_success'] == 1) {
                // 上传过 且上传完成 属于重复上传相同文件 可能文件名不同
                json(204);
            }
        }

        // 检查 上传过同名文件
        $sql = 'select * from upload_file where filename = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($filename, $user['identification']));
        if ($stmt->rowCount()) {
            json(208, '禁止同名');
        }

        // 创建记录
        $temp_dir = md5($file_md5 . time());
        $temp_id = md5($file_md5 . $temp_dir . time());
        $sql = 'insert into upload_file(filename,save_filename,md5_file,upload_time,userof,ip,switch,upload_success,totalpieces,ok_chunk,temp_dir,temp_id) value(?,?,?,?,?,?,?,?,?,?,?,?);';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($filename, 'none', $file_md5, time(), $user['identification'], $_SERVER['REMOTE_ADDR'], 0, 0, $totalPieces, 0, $temp_dir, $temp_id));
        if ($stmt->rowCount()) {
            mkdir("./uploads_temp/" . $temp_dir, 0755, true);
            json(0, 'ok', array('upload_id' => $temp_id));
        }
        json(-1);

        break;
    case 'upload_part':
        if (!file_exists('./uploads_temp')) {
            mkdir('./uploads_temp', 0755);
        }
        header('Access-Control-Allow-Origin:*');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $file = $_FILES['file'];
        $chunk = $_GET['chunk'];
        $upload_id = $_GET['upload_id'];
        $user = getUser();

        $sql = 'select * from upload_file where temp_id = ? and upload_success = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($upload_id, 0, $user['identification']));
        if (!$stmt->rowCount()) {
            json(-1, '错误参数');
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (file_put_contents('./uploads_temp/' . $result['temp_dir'] . '/' . $chunk, file_get_contents($file['tmp_name']))) {
            $sql = 'update upload_file set ok_chunk = ? where temp_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($chunk, $upload_id));
            if ($stmt->rowCount()) {
                json(0);
            }
            unlink('./uploads_temp/' . $result['temp_dir'] . '/' . $chunk);
            json(-1, '文件保存失败');
        }
        json(-1);
        break;
    case 'upload_save':
        $upload_id = $_POST['upload_id'];
        $user = getUser();

        if (!file_exists('./uploads')) {
            mkdir('./uploads', 0755);
        }

        $sql = 'select * from upload_file where temp_id = ? and upload_success = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($upload_id, 0, $user['identification']));
        if (!$stmt->rowCount()) {
            json(-1, '错误参数');
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // 合并文件 检查文件md5 转移文件位置 修改数据库信息
        if (file_exists('./uploads_temp/' . $result['temp_dir'] . '/file')) {
            unlink('./uploads_temp/' . $result['temp_dir'] . '/file'); // 原有删除
        }
        for ($i = 1; $i < $result['totalpieces'] + 1; $i++) {
            file_put_contents('./uploads_temp/' . $result['temp_dir'] . '/file', file_get_contents('./uploads_temp/' . $result['temp_dir'] . '/' . $i), FILE_APPEND);
        }
        if (md5_file('./uploads_temp/' . $result['temp_dir'] . '/file') != $result['md5_file']) {
            json(
                205,
                'file_md5 error',
                array(
                    'temp_md5' => md5_file('./uploads_temp/' . $result['temp_dir'] . '/file'),
                    'sql_md5' => $result['md5_file']
                )
            );
        }

        $save_filename = md5($result['md5_file'] . time() . $user['identification']);
        if (file_put_contents('./uploads/' . $save_filename, file_get_contents('./uploads_temp/' . $result['temp_dir'] . '/file'))) {
            $sql = 'update upload_file set save_filename = ? , upload_success = ? , switch = ? where temp_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($save_filename, 1, 1, $upload_id));
            if (!$stmt->rowCount()) {
                unlink('./uploads/' . $save_filename);
                json(207);
            }
            deleteDirectory('./uploads_temp/' . $result['temp_dir']);
            json(0);
        } else {
            json(206);
        }
        break;
    case 'deleteFile':
        $user = getUser();

        $secondPassword = $_POST['secondPassword'];
        if ($user['secondpassword'] != md5($secondPassword)) {
            json(-1, '二级密码错误');
        }

        $filename = $_POST['filename'];
        $sql = 'select * from upload_file where userof = ? and filename = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification'], $filename));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $md5_file = $result['md5_file'];
        $save_filename = $result['save_filename'];
        $temp_dir = $result['temp_dir'];
        $upload_success = $result['upload_success'];
        if ($stmt->rowCount()) {
            $sql = 'delete from upload_file where userof = ? and filename = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($user['identification'], $filename));
            if (!$stmt->rowCount()) {
                json(-1, '操作失败');
            }

            // 未完成上传判断
            if (!$upload_success) {
                deleteDirectory('./uploads_temp/' . $temp_dir);
                json(0);
            }

            // 查询还有没有用户有这个文件的记录 没有就直接删除文件 否则留
            $sql = 'select * from upload_file where md5_file = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($md5_file));
            if (!$stmt->rowCount()) {
                unlink('./uploads/' . $save_filename);
            }

            json(0);
        } else {
            json(-1, '错误参数');
        }
        json(-1);

        break;
    case 'getAppVersionInformation':
        $user = getUser();
        $identification = $_POST['identification'];

        $sql = 'select * from appversion where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($identification));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stmt->rowCount()) {
            json(-1, '错误的版本标识');
        }
        $appof = $result['appof'];
        $update_file = $result['update_file'];
        $install_file = $result['install_file'];
        $switch = $result['switch'];
        $only_role = $result['only_role'];

        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        json(
            0,
            'ok',
            array(
                'versionswitch' => $switch == 1 ? true : false,
                'update_file' => $update_file,
                'install_file' => $install_file,
                'only_role' => $only_role
            )
        );

        break;
    case 'getUserFileAll':
        // 与getFileList不同 这个只返回能用的文件的文件名
        $user = getUser();
        $sql = 'select * from upload_file where userof = ? and switch = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification'], 1));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $file = [];
        foreach ($result as $row) {
            array_push(
                $file,
                array(
                    'filename' => $row['filename'],
                )
            );
        }
        json(0, 'ok', array('items' => $file));

        break;
    case 'setAppVersionInformation':
        $user = getUser();
        $identification = $_POST['identification'];
        $versionswitch = $_POST['versionswitch'];
        $update_file = $_POST['update_file'];
        $install_file = $_POST['install_file'];
        $only_role = $_POST['only_role'];

        $sql = 'select * from appversion where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($identification));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stmt->rowCount()) {
            json(-1, '错误的版本标识');
        }
        $appof = $result['appof'];

        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        $sql = 'update appversion set switch = ? , update_file = ? , install_file = ? , only_role = ? where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($versionswitch, $update_file, $install_file, $only_role, $identification));
        if (!$stmt->rowCount()) {
            json(-1, '操作失败或<strong style="color:green">用户提交了相同的数据</strong>');
        }
        json(0);

        break;
    case 'getAuthList':
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $w = ($page - 1) * $limit;
        $app = [];
        $authlist = [];
        $count = 0;
        $user = getUser();
        $appof = $_GET['appof'];

        $sql = 'select identification,appname from app where host = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $app = $stmt->fetchAll();

        if (isset($appof) && !empty($appof)) {
            // 点击了筛选APP 即使空选了也会设置appof 只不过为空
            $sql = 'select identification,appname from app where host = ? and identification = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($user['identification'], $appof));
            // 重新赋值
            $app = $stmt->fetchAll();
        }

        foreach ($app as $row1) {
            $sql = 'select * from appauth where appof = ? order by activation_time desc limit ?,?';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $row1['identification']);
            $stmt->bindParam(2, $w, PDO::PARAM_INT);
            $stmt->bindParam(3, $limit, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetchAll();
            foreach ($result as $row2) {
                $sql = 'select * from appversion where appof = ? and switch = 1 order by create_time';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($row1['identification']));
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $version = [];
                $n = 0;
                $i = 0;
                foreach ($result as $row) {
                    array_push($version, $row['version']);
                    if ($row['version'] == $app_version) {
                        $i = $n;
                    }
                    $n++;
                }
                if ($version[(count($version) - 1)] == $row2['version']) {
                    $version1 = '<span style="color:green">' . $row2['version'] . '</span>';
                } else {
                    $version1 = '<span style="color:red">' . $row2['version'] . '</span>';
                }

                $heartbeat = date('Y-m-d H:i:s', $row2['heartbeat']);
                if ((time() - $row2['heartbeat']) <= 60) {
                    $heartbeat = '<span style="color:green">' . date('Y-m-d H:i:s', $row2['heartbeat']) . '</span>';
                }

                if ($row2['activation_time'] != 0) {
                    $activation_time = date('Y-m-d H:i:s', $row2['activation_time']);
                    if ($row2['end_time'] < time()) {
                        $end_time = '<span style="color:red">' . date('Y-m-d H:i:s', $row2['end_time']);
                    } else {
                        $end_time = '<span style="color:green">' . date('Y-m-d H:i:s', $row2['end_time']) . '</span>';
                    }
                } else {
                    $activation_time = '<span style="color:red">未激活</span>';
                    $end_time = '<span style="color:red">未激活</span>';
                    $heartbeat = '<span style="color:red">未激活</span>';
                    $version1 = '<span style="color:red">未激活</span>';
                }

                $auth_time = $row2['auth_time'];
                $auth_time_type = $row2['auth_time_type'];
                switch ($auth_time_type) {
                    case 'minute':
                        $auth_time_type = '分钟';
                        break;
                    case 'hour':
                        $auth_time_type = '小时';
                        break;
                    case 'day':
                        $auth_time_type = '天';
                        break;
                    case 'month':
                        $auth_time_type = '月';
                        break;
                    case 'year':
                        $auth_time_type = '年';
                        break;
                    case 'forever':
                        $auth_time_type = '永久';
                        if ($row2['activation_time'] != 0) {
                            $end_time = '<span style="color:green">永久</span>';
                        }
                        $effective_time = '永久';
                        break;
                }
                if ($auth_time_type != '永久') {
                    $effective_time = $auth_time . $auth_time_type;
                }

                array_push(
                    $authlist,
                    array(
                        'auth_code' => $row2['auth_code'],
                        'appof' => $row1['appname'],
                        'activation_time' => $activation_time,
                        'end_time' => $end_time,
                        'switch' => $row2['switch'] == 1 ? '<span style="color:green">可用</span>' : '<span style="color:red">禁用</span>',
                        'role' => $row2['role'],
                        'effective_time' => $effective_time,
                        'heartbeat' => $heartbeat,
                        'version' => $version1,
                    )
                );
            }

            $sql = 'select * from appauth where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $row1['identification']);
            $stmt->execute();
            $count += $stmt->rowCount();
        }

        json(0, 'ok', array('items' => $authlist, 'count' => $count));
        break;
    case 'addAuthCode':
        $addAuth_num = (int) $_POST['addAuth_num'];
        $appof = $_POST['appof'];
        $auth_time = (int) $_POST['auth_time'];
        $auth_time_type = $_POST['auth_time_type'];
        $role = (int) $_POST['role'];
        if (empty($addAuth_num) || empty($appof) || empty($auth_time) || empty($auth_time_type) || empty($role)) {
            json(-1, '参数不完整');
        }
        if ($addAuth_num <= 0) {
            json(-1, '错误数量');
        }
        if ($addAuth_num > 9999) {
            json(-1, '数量多');
        }
        if ($auth_time <= 0) {
            json(-1, '错误有效期');
        }
        $user = getUser();

        $allow_auth_time_type = ['minute', 'hour', 'day', 'month', 'year', 'forever'];
        if (!in_array($auth_time_type, $allow_auth_time_type)) {
            json(-1, '非法参数');
        }

        // 校验软件主人
        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        $sql = 'insert into appauth(auth_code,appof,switch,activation_time,end_time,role,auth_time,auth_time_type) value(?,?,?,?,?,?,?,?);';
        $stmt = $pdo->prepare($sql);
        for ($i = 0; $i < $addAuth_num; $i++) {
            $auth_code = md5($user['identification'] . time() . $appof . rand(0, 999999) . rand(0, 999999) . rand(0, 999999));
            $auth_code = md5($auth_code);
            $stmt->execute(array($auth_code, $appof, 0, 0, 0, $role, $auth_time, $auth_time_type));
            if (!$stmt->rowCount()) {
                json(-1);
            }
        }
        json(0);

        break;
    case 'deleteAuth':
        $auth_code = $_POST['auth_code'];
        $secondPassword = $_POST['secondPassword'];
        $user = getUser();

        // 校验二级密码
        if ($user['secondpassword'] != md5($secondPassword)) {
            json(-1, '二级密码错误');
        }

        // 校验用户
        $sql = 'select * from appauth where auth_code = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth_code));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appof = $result['appof'];
        $id = $result['id'];

        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        $sql = 'delete from appauth where id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($id));
        if ($stmt->rowCount()) {
            json(0);
        }
        json(-1);

        break;
    case 'getAuthInformation':
        $auth_code = $_POST['auth_code'];
        $user = getUser();
        // 校验用户
        $sql = 'select * from appauth where auth_code = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth_code));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appof = $result['appof'];
        $role = $result['role'];
        $authswitch = $result['switch'] == 1 ? true : false;

        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        json(
            0,
            'ok',
            array(
                'authswitch' => $authswitch,
                'role' => $role
            )
        );

        break;
    case 'setAuthInformation':
        $auth_code = $_POST['auth_code'];
        $auth_time = (int) $_POST['auth_time'];
        $auth_time_type = $_POST['auth_time_type'];
        $role = (int) $_POST['role'];
        $switch = $_POST['AuthSwitch'];
        $user = getUser();

        // 校验用户 授权码激活状态 未激活禁止修改
        $sql = 'select * from appauth where auth_code = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth_code));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appof = $result['appof'];
        $id = $result['id'];
        $end_time = $result['end_time'];
        if ($result['activation_time'] == 0) {
            json(-1, '未激活验证码禁止修改');
        }

        $sql = 'select * from app where identification = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($appof));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['host'] != $user['identification']) {
            json(-1, '非法请求');
        }

        if ($auth_time != 0) {
            $sql = 'select * from appauth where auth_code = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($auth_code));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['auth_time_type'] == 'forever') {
                json(-1, '有病？永久还加你妈的时间');
            }

            $allow_auth_time_type = ['minute', 'hour', 'day', 'month', 'year', 'forever'];
            if (!in_array($auth_time_type, $allow_auth_time_type)) {
                json(-1, '非法参数');
            }
            $addTime = 0;
            switch ($auth_time_type) {
                case 'minute':
                    $addTime += $auth_time * 60;
                    break;
                case 'hour':
                    $addTime += $auth_time * 3600;
                    break;
                case 'day':
                    $addTime += $auth_time * 86400;
                    break;
                case 'month':
                    $addTime += $auth_time * 2592000;
                    break;
                case 'year':
                    $addTime += $auth_time * 31557600;
                    break;
                case 'forever':
                    $sql = 'update appauth set auth_time_type = "forever" , end_time = -1 where id = ?';
                    break;
            }

            if ($end_time < time()) {
                $addTime += time();
            } else {
                $addTime += $end_time;
            }

            if ($auth_time_type != 'forever') {
                $sql = 'update appauth set end_time = ? where id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($addTime, $id));
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($id));
            }

            if (!$stmt->rowCount()) {
                json(-1, '操作失败或<strong style="color:green">用户提交了相同的数据</strong>');
            }
        }

        $sql = 'update appauth set role = ? , switch = ? where id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($role, $switch, $id));
        json(0);

        break;
    case 'getHomeInformation':
        $user = getUser();
        $api_num = (int) $user['api_connect_num'];

        $sql = 'select * from app where host = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $app = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $app_num = $stmt->rowCount();

        $appversion_num = 0;
        $auth_num = 0;
        foreach ($app as $row) {
            $sql = 'select * from appversion where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($row['identification']));
            $appversion_num += $stmt->rowCount();

            $sql = 'select * from appauth where appof = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($row['identification']));
            $auth_num += $stmt->rowCount();
        }

        json(
            0,
            'ok',
            array(
                'auth_num' => $auth_num,
                'app_num' => $app_num,
                'api_num' => $api_num,
                'appversion_num' => $appversion_num
            )
        );

        break;
    case 'getRemoteVariables':
        $user = getUser();

        $sql = 'select * from remote_variables where userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $stmt->rowCount();
        $variables = [];

        foreach ($result as $row) {
            array_push(
                $variables,
                array(
                    'name' => $row['name'],
                    'content' => $row['content'],
                    'create_time' => date('Y-m-d H:i:s', $row['create_time']),
                    'switch' => $row['switch'] == 1 ? '<span style="color:green">可用</span>' : '<span style="color:red">不可用</span>',
                    'notes' => $row['notes']
                )
            );
        }

        json(
            0,
            'ok',
            array(
                'count' => $count,
                'items' => $variables
            )
        );

        break;
    case 'addRemote-variables':
        $user = getUser();
        $name = $_POST['name'];
        $content = $_POST['content'];
        $notes = $_POST['notes'];

        // 同名校验
        $sql = 'select * from remote_variables where name = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($name, $user['identification']));
        if ($stmt->rowCount()) {
            json(-1, '禁止重名');
        }

        $sql = 'insert into remote_variables(name,content,notes,userof,switch,create_time) values(?,?,?,?,?,?);';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($name, $content, $notes, $user['identification'], 1, time()));
        if ($stmt->rowCount()) {
            json(0);
        }

        json(-1);

        break;
    case 'deleteRemote-variables':
        $user = getUser();
        $name = $_POST['name'];
        $secondPassword = $_POST['secondPassword'];

        if ($user['secondpassword'] != md5($secondPassword)) {
            json(-1, '二级密码错误');
        }

        $sql = 'delete from remote_variables where name = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($name, $user['identification']));
        if ($stmt->rowCount()) {
            json(0);
        }

        json(-1);

        break;
    case 'getRemote-variablesInformation':
        $user = getUser();
        $name = $_POST['name'];

        $sql = 'select * from remote_variables where name = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($name, $user['identification']));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        json(
            0,
            'ok',
            array(
                'content' => $result['content'],
                'varSwitch' => $result['switch'] == 1 ? true : false,
                'notes' => $result['notes']
            )
        );

        break;
    case 'setRemote-variablesInformation':
        $user = getUser();
        $name = $_POST['name'];
        $varSwitch = $_POST['varSwitch'];
        $notes = $_POST['notes'];
        $content = $_POST['content'];

        $sql = 'update remote_variables set content = ? , notes = ? , switch = ? where name = ? and userof = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($content, $notes, $varSwitch, $name, $user['identification']));
        if ($stmt->rowCount()) {
            json(0);
        }
        json(-1, '操作失败或<strong style="color:green">用户提交了相同的数据</strong>');

        break;
    case 'getHomeTableData':
        $user = getUser();

        $sql = 'select * from app where host = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $series = [];
        foreach ($result as $row) {
            array_push($data, $row['appname']);
            $data1 = [0, 0, 0, 0, 0, 0, 0];
            for ($i = 1; $i < 8; $i++) {
                if (file_exists('./data/autowork/user_apitimes/' . $user['identification'] . '/' . $row['identification'] . '/' . $i)) {
                    $data1[($i - 1)] = (int) file_get_contents('./data/autowork/user_apitimes/' . $user['identification'] . '/' . $row['identification'] . '/' . $i);
                }
            }
            array_push(
                $series,
                array(
                    'name' => $row['appname'],
                    'type' => 'line',
                    'data' => $data1
                )
            );
        }

        json(
            0,
            'ok',
            array(
                'data' => $data,
                'series' => $series
            )
        );

        break;

    case 'getRequest-forwarding':
        $user = getUser();

        $sql = 'select * from request_forwarding where userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $stmt->rowCount();
        $request_forwarding = [];

        foreach ($result as $row) {
            array_push(
                $request_forwarding,
                array(
                    'out_link' => $row['out_link'],
                    'type' => $row['type'],
                    'create_time' => date('Y-m-d H:i:s', $row['create_time']),
                    'switch' => $row['switch'] == 1 && $row['auditing'] == 1 ? '<span style="color:green">可用</span>' : '<span style="color:red">不可用</span>',
                    'notes' => $row['notes'],
                    'use_times' => $row['use_times'],
                    'auditing' => $row['auditing'] == 1 ? '<span style="color:green">人工审核通过</span>' : '<span style="color:red">人工审核未通过/未审核</span>',
                    'link' => $row['link']
                )
            );
        }

        json(
            0,
            'ok',
            array(
                'count' => $count,
                'items' => $request_forwarding
            )
        );

        break;

    case 'addRequest-forwarding':
        $user = getUser();
        $create_time = time();
        $notes = $_POST['notes'];
        $type = $_POST['type'];
        $link = $_POST['link'];
        $out_link = md5($notes . $type . $link . $create_time . rand(0, $create_time));
        $ip = $_SERVER['REMOTE_ADDR'];

        if ($type != 'get' && $type != 'post') {
            json(-1, '数据不合规');
        }

        $sql = 'insert into request_forwarding(notes,type,link,out_link,ip,switch,create_time,userof,auditing,use_times) value(?,?,?,?,?,?,?,?,?,?);';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($notes, $type, $link, $out_link, $ip, 1, $create_time, $user['identification'], 1, 0));
        if ($stmt->rowCount()) {
            json(0);
        }

        break;

    case 'getRequest-forwardingInformation':
        $user = getUser();
        $out_link = $_POST['out_link'];

        $sql = 'select * from request_forwarding where userof = ? and out_link = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification'], $out_link));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        json(
            0,
            'ok',
            array(
                'notes' => $result['notes'],
                'rfswitch' => $result['switch'] == 1 ? true : false,
                'link' => $result['link'],
                'type' => $result['type']
            )
        );

        break;

    case 'setRequest-forwardingInformation':
        $user = getUser();
        $out_link = $_POST['out_link'];

        $sql = 'select * from request_forwarding where userof = ? and out_link = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($user['identification'], $out_link));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_link = $result['link'];

        $notes = $_POST['notes'];
        $link = $_POST['link'];
        $type = $_POST['type'];
        $switch = $_POST['switch'];

        $sql = 'update request_forwarding set notes = ? , link = ? , type = ? , switch = ? where out_link = ? and userof = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($notes,$link,$type,$switch,$out_link,$user['identification']));
        if (!$stmt->rowCount()) {
            json(-1, '操作失败或<strong style="color:green">用户提交了相同的数据</strong>');
        }

        if($last_link != $link){
            $sql = 'update request_forwarding set auditing = 1 where out_link = ? and userof = ?;';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($out_link,$user['identification']));
        }

        json(0);

        break;

    case 'deleteRequest-forwarding':
        $user = getUser();
        $out_link = $_POST['out_link'];
        $secondPassword = $_POST['secondPassword'];

        if ($user['secondpassword'] != md5($secondPassword)) {
            json(-1, '二级密码错误');
        }

        $sql = 'delete from request_forwarding where out_link = ? and userof = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($out_link, $user['identification']));
        if ($stmt->rowCount()) {
            json(0);
        }

        json(-1);
        break;

    case 'resetAuthCode':
        $user = getUser();
        $auth_code = $_POST['auth_code'];
        $secondPassword = $_POST['secondPassword'];

        if ($user['secondpassword'] != md5($secondPassword)) {
            json(-1, '二级密码错误');
        }

        $sql = 'select * from appauth where auth_code = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth_code));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result['activation_time'] == 0){
            json(-1, '你脑子有泡吧？');
        }

        $sql = 'update appauth set switch = 0 , activation_time = 0 , heartbeat = 0 , version = 0 , end_time = 0 where auth_code = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($auth_code));
        if ($stmt->rowCount()) {
            json(0);
        }

        json(-1,'服务器错误');
        break;

    case 'changePassword':
        $user = getUser();

        $old_password = md5($_POST['old_password']);
        $new_password = md5($_POST['new_password']);
        $again_password = md5($_POST['again_password']);

        if($old_password != $user['password']){
            json(-1, '旧密码错误');
        }
        if($new_password != $again_password){
            json(-1, '两次密码不同');
        }

        $sql = 'update user set password = ? where id = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($new_password,$user['id']));
        if ($stmt->rowCount()) {
            session_destroy();
            json(0);
        }

        json(-1);

        break;
    case 'changeSecondPassword':
        $user = getUser();

        $old_password = md5($_POST['old_password']);
        $new_password = md5($_POST['new_password']);
        $again_password = md5($_POST['again_password']);

        if($old_password != $user['secondpassword']){
            json(-1, '旧密码错误');
        }
        if($new_password != $again_password){
            json(-1, '两次密码不同');
        }

        $sql = 'update user set secondpassword = ? where id = ?;';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($new_password,$user['id']));
        if ($stmt->rowCount()) {
            session_destroy();
            json(0);
        }

        json(-1);

        break;

    default:
        json(404, '请求参数错误');
        break;
}
function json($code, $msg = null, $data = null)
{
    header("Content-Type:application/json; charset=utf-8");
    echo json_encode(array("code" => $code, "msg" => $msg, "data" => $data));
    exit;
}
function deleteDirectory($dir)
{
    // 删除文件夹 https://blog.csdn.net/Klaus_S/article/details/131439581
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteDirectory("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
exit;
?>