jconfirm.defaults = {
    theme: 'modern',
    useBootstrap: false,
    content: '空的弹窗内容',
};
function showMsg(text, icon, hideAfter) {
    /**
     * jQuery_toast 信息显示
     * @param 显示文本 text
     * @param 图标 icon
     * @param 自动关闭时间 hideAfter
     */
    if (heading == undefined) {
        var heading = "提示";
    }
    $.toast({
        text: text,
        heading: heading,
        icon: icon,
        showHideTransition: 'fade',
        allowToastClose: true,
        hideAfter: hideAfter,
        stack: 8,
        position: 'top-center',
        textAlign: 'left',
        loader: true,
        loaderBg: '#ffffff',
    });
}
function dataError(text = '网络异常', icon = "fa fa-exclamation-circle") {
    /**
     * 报错 刷新页面
     * @param 自定义显示信息 text
     * @param 图标 icon
    */
    console.log('ERROR : ' + text)
    $.alert({
        title: 'Warn',
        content: '<strong style="color:red">[' + text + ']</strong>',
        type: 'red',
        icon: icon,
        buttons: {
            ok: function () {
            }
        }
    })
}
function ajax(data, func) {
    /**
     * ajax 异步向/ajax.php获取数据
     * @param 请求数据 data 
     * @param 回调函数 func
     */
    $.ajax({
        url: '/ajax.php',
        method: 'post',
        cache: false,
        data: data,
        success: function (res) {
            console.log(res)
            if (res.code != 0) {
                errorCode(res.code, res.msg);
                return false;
            }
            func(res);
        },
        error: function () {
            dataError();
        }
    })
}
function ajaxHtml(t, h) {
    /**
     * jQuery_confirm 显示弹窗网页
     * @param 标题 t
     * @param 需要的html文件名 h
     */
    $.alert({
        title: t,
        content: 'URL:/ajax.php?do=ajaxHtml&request_of=user&html=' + h,
        type: 'green',
        buttons: {
            关闭: function () { }
        }
    })
}
function errorCode(code, msg = null) {
    switch (code) {
        case -1:
            dataError('服务器操作失败，服务器信息：' + msg, 'fa fa-bug');
            break;
        case 100:
            dataError('您没登录', 'fa fa-user-times');
            window.location.href = '/user'
            break;
        case 101:
            dataError('非法用户！', 'fa fa-user-times');
            break;
        case 102:
            dataError('您的账户已被封禁', 'fa fa-user-times');
            break;
        case 103:
            $.alert({
                title: 'Warn',
                content: '您的账户尚未激活' + '<br><strong style="color:red">[5s后自动跳转激活页面]</strong>',
                autoClose: 'ok|5000',
                type: 'red',
                icon: 'fa fa-user-times',
                buttons: {
                    ok: {
                        isHidden: true,
                        action: function () {
                            window.location.href = '/user/index.php#/page/useractivation.html';
                        }
                    }
                }
            })
            break;
        case 103:
            dataError('您已登录', 'fa fa-user-times');
            break;
        case 404:
            dataError('服务器找不到请求的功能', 'fa fa-bug');
            break;
    }
}