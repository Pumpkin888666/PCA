<?php
function getAuth($auth_code)
{
    // 获取授权码的所有信息
    global $pdo;
    $auth = array(
        'auth' => [],
        'app' => []
    );

    $sql = 'select * from appauth where auth_code = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($auth_code));
    $auth['auth'] = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$auth['auth']) {
        json(-1,'授权码核验失败');
    }

    $sql = 'select * from app where identification = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($auth['auth']['appof']));
    $auth['app'] = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$auth['app']) {
        json(-1, '软件信息查询错误');
    }

    return $auth;
}
?>