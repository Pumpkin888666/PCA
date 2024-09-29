<?php
/**
 * @author Pumpkin aswdfgyhj@163.com
 * @var $ol // out_link
 * @var $post_data // post参数
 * @var $pdo // 变量指向pdo数据库操作
 */

function request_forwarding($ol,$post_data,$pdo){
    $sql = 'select * from request_forwarding where out_link = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($ol));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stmt->rowCount()) {
        return '接口无法使用';
    }

    if ($result['switch'] == 0 || $result['auditing'] == 0) {
        return '接口无法使用';
    }

    $sql = 'update request_forwarding set use_times = use_times + 1 where out_link = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($ol));


    if ($result['type'] == 'get') {
        return file_get_contents('https://' . $result['link']);
    } else if ($result['type'] == 'post') {
        $r = PCA_send_post('https://' . $result['link'], $post_data);
        return $r;
    } else {
        return 'System fail';
    }
}

function send_post($url, $post_data)
{
    // 发送POST请求 普通
    $postdata = http_build_query($post_data);
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $postdata,
            'timeout' => 15 * 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result;
}

?>