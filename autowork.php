<?php
ini_set('display_errors', 'Off'); // 关闭报错
define('ROOTS', dirname(__FILE__) . '/');
define('ROOT', dirname(ROOTS) . '/');
include './data/database/config.php';
include './data/database/db.php';

$work = $_GET['work'];
$key = $_GET['key'];

if ($key != file_get_contents('./data/autowork.key')) {
    echo 'key_error';
    exit;
}

// autowork 文件夹检测
if (!file_exists('./data/autowork')) {
    mkdir('./data/autowork', 0755);
}

switch ($work) {
    case 'save_user_api_information':
        $success = 0;
        $error = 0;
        $total = 0;
        $user = [];
        $r = $pdo->query('select identification from user;');
        foreach ($r as $row) {
            array_push($user, $row['identification']);
        }

        foreach ($user as $id) {
            $app = [];
            $r = $pdo->query('select identification,api_times from app where host = "' . $id . '";');
            foreach ($r as $row) {
                array_push(
                    $app,
                    array(
                        'identification' => $row['identification'],
                        'api_times' => $row['api_times'],
                    )
                );
            }
            if (!is_dir('./data/autowork/user_apitimes')) {
                mkdir('./data/autowork/user_apitimes', 0755);
            }
            if (!is_dir('./data/autowork/user_apitimes/' . $id)) {
                mkdir('./data/autowork/user_apitimes/' . $id, 0755);
            }

            foreach ($app as $row) {
                if (!is_dir('./data/autowork/user_apitimes/' . $id . '/' . $row['identification'])) {
                    mkdir('./data/autowork/user_apitimes/' . $id . '/' . $row['identification'], 0755);
                }
                if (file_put_contents('./data/autowork/user_apitimes/' . $id . '/' . $row['identification'] . '/' . date('N'), $row['api_times'])) {
                    $success++;
                } else {
                    $error++;
                }
                $total++;
            }
        }

        echo 'ok:total=>' . $total . ';success=>' . $success . ';error=>' . $error;

        break;
    case 'clear_everyday_app_api_times':
        $pdo->exec('update app set api_times = 0;');
        echo 'clear_everyday_app_api_times ok!';
        break;
    case 'reset_0_auth':
        $pdo->exec('update appauth set switch = 0 , activation_time = 0 , heartbeat = 0 , version = 0 , end_time = 0 where version = 0 and ' . time() . ' - activation_time >= 600;');
        echo 'reset_0_auth ok!';
        break;
}

?>