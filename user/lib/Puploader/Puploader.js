/**
 * @author Pumpkin
 * @qq 2897286331
 * @time 2024-1-13
*/
// 需要引入 Jquery3.7.0 usermain.js spark-md5.js
$("#Puploader_fileInput").change(function () {
    $("#Puploader_fileChoose").slideUp();
    Puploader_upload();
})

var bytesPerPiece = 1024 * 1024 * 5; // 每个文件切片大小
var totalPieces;

function Puploader_upload() {
    var blob = document.getElementById("Puploader_fileInput").files[0];
    var filename = blob.name;
    var filesize = blob.size;
    totalPieces = Math.ceil(filesize / bytesPerPiece); //计算文件切片总数

    // 计算文件md5
    var spark = new SparkMD5.ArrayBuffer();
    var fileReader = new FileReader();
    fileReader.readAsArrayBuffer(blob);
    fileReader.onload = function (e) {
        spark.append(e.target.result);
        let hash = spark.end();
        console.log('File hash:', hash);

        $.ajax({
            url: '/ajax.php',
            method: 'POST',
            cache: false,
            data: {
                do: 'upload_prepare',
                file_md5: hash,
                totalPieces: totalPieces,
                filename: filename
            },
            success: function (res) {
                if (res.code == 204) {
                    dataError('您已上传过相同文件', 'fa fa-file-o');
                    return;
                }
                if (res.code == 200) {
                    $.alert({
                        title: 'SUCCESS',
                        content: '云端有相同内容文件，已添加数据库记录。',
                        icon: 'fa fa-smile-o',
                        type: 'green',
                        buttons: {
                            关闭: function () { }
                        }
                    })
                    return;
                }
                if (res.code == 201) {
                    $.alert({
                        title: 'UM...',
                        content: '云端有相同内容文件，但添加数据库记录失败',
                        type: 'red',
                        buttons: {
                            关闭: function () { }
                        }
                    })
                    return;
                }
                if (res.code == 208) {
                    dataError('禁止重名');
                    return;
                }
                if (res.code == 203) {
                    ok_chunk = res.data.ok_chunk; // 断点续传
                } else {
                    ok_chunk = 0;
                }
                upload_id = res.data.upload_id; // 上传凭证
                console.log('准备完成 进行上传');
                Puploader_startUpload(ok_chunk, upload_id);

            },
            error: function (res) {
                dataError();
            },
        })
    };
}

async function Puploader_startUpload(ok_chunk, upload_id) {
    // 上传

    // 初始化一些参数
    lastTime = 0;
    lastSize = 0;
    index = 0;
    totalPieces = 0;
    var blob = document.getElementById("Puploader_fileInput").files[0];
    var start = 0;
    var end;
    var index = 0;
    var filesize = blob.size;
    var filename = blob.name;

    totalPieces = Math.ceil(filesize / bytesPerPiece); //计算文件切片总数

    while (start < filesize) {
        end = start + bytesPerPiece;
        if (end > filesize) {
            end = filesize;
        }

        var chunk = blob.slice(start, end);//切割文件
        var sliceIndex = blob.name + index;
        var formData = new FormData();
        formData.append("file", chunk, filename);
        if (index + 1 > ok_chunk) {
            // 判断有没有上传过s
            await $.ajax({
                url: '/ajax.php?do=upload_part&chunk=' + (index + 1) + '&upload_id=' + upload_id,
                type: 'POST',
                cache: false,
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    console.log(res)
                    if (res.code == -1) {
                        dataError('分片' + (index + 1) + '上传失败！');
                    }
                },
                error: function (res) {
                    dataError();
                },
                xhr: function () {
                    var xhr = new XMLHttpRequest();
                    //使用XMLHttpRequest.upload监听上传过程，注册progress事件，打印回调函数中的event事件
                    xhr.upload.addEventListener('progress', function (e) {
                        var progressRate = ((index + 1) / totalPieces) * 100 + '%';
                        //通过设置进度条的宽度达到效果
                        $('.Puploader_progress > div').css('width', progressRate);
                        var span = Math.round((index + 1) / totalPieces * 100);
                        $("#Puploader_progress_span").text(span);

                        /*计算间隔*/
                        var nowTime = new Date().getTime();
                        var intervalTime = (nowTime - lastTime) / 1000;//时间单位为毫秒，需转化为秒
                        var intervalSize = filesize - lastSize;
                        /*重新赋值以便于下次计算*/
                        lastTime = nowTime;
                        lastSize = e.loaded;
                        /*计算速度*/
                        var speed = intervalSize / intervalTime;
                        var bSpeed = speed;//保存以b/s为单位的速度值，方便计算剩余时间
                        var units = 'b/s';//单位名称
                        if (speed / 1024 > 1) {
                            speed = speed / 1024;
                            units = 'k/s';
                        }
                        if (speed / 1024 > 1) {
                            speed = speed / 1024;
                            units = 'M/s';
                        }
                        $("#Puploader_progress_speed").text(speed.toFixed(1) + units);
                    })

                    return xhr;
                }
            })
        }
        start = end;
        index++;
    }
    Puploader_save(upload_id);
}

function Puploader_save(upload_id) {
    // 保存文件
    ajax({ do: 'upload_save', upload_id: upload_id }, function (res) {
        if (res.code == 0) {
            $.alert({
                title: '成功',
                content: '保存文件成功',
                type: 'green',
                icon: 'fa fa-smile-o'
            })
        }
        if (res.code == 205) {
            $.alert({
                title: 'FAIL',
                content: '云端文件MD5核对失败，请重新上传',
                type: 'red',
            })
        }
        if (res.code == 206) {
            $.alert({
                title: 'FAIL',
                content: '云端文件保存失败',
                type: 'red',
            })
        }
        if (res.code == 207) {
            $.alert({
                title: 'FAIL',
                content: '云端数据更新失败',
                type: 'red',
            })
        }
    })
}