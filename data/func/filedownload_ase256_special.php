<?php

function file_aes256($str, $model)
{
    // 缩水版 文件下载特供
    $key = 0;
    for ($i = 0; $i < 16; $i++) {
        $key .= md5($key . time());
    }
    if($model == 'encrypt'){
        for ($i = 0; $i < 8; $i++) {
            $str = openssl_encrypt($str, 'AES-256-CBC', $key, 0, 'I very like you.');
        }
        return $str;
    }else if ($model == 'decrypt') {
        for ($i = 0; $i < 8; $i++) {
            $str = openssl_decrypt($str, 'AES-256-CBC', $key, 0, 'I very like you.');
        }
        return $str;
    }else{
        return false;
    }
}


?>