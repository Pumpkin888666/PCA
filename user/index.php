<?php
session_start();
if (!isset($_SESSION["User"])) {
    header("location:page/login.html");
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>PCA UserCenter</title>
    <!-- 网站关键字 -->
    <meta name="keywords" content="">
    <!-- 网站描述 -->
    <meta name="description" content="">
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Access-Control-Allow-Origin" content="*">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="lib/layui-v2.5.5/css/layui.css" media="all">
    <link rel="stylesheet" href="lib/font-awesome-4.7.0/css/font-awesome.min.css" media="all">
    <link rel="stylesheet" href="lib/jquery-confirm-3.3.4/jquery-confirm.min.css">
    <link rel="stylesheet" href="lib/jquery-toast/jquery.toast.min.css">
    <link rel="stylesheet" href="css/layuimini.css?v=2.0.1" media="all">
    <link rel="stylesheet" href="css/themes/default.css" media="all">
    <link rel="stylesheet" href="css/public.css" media="all">
    <!--[if lt IE 9]>
    <script src="https://cdn.staticfile.org/html5shiv/r29/html5.min.js"></script>
    <script src="https://cdn.staticfile.org/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style id="layuimini-bg-color">
    </style>
</head>

<body class="layui-layout-body layuimini-all">
    <div class="layui-layout layui-layout-admin">

        <div class="layui-header header">
            <div class="layui-logo layuimini-logo layuimini-back-home"></div>

            <div class="layuimini-header-content">
                <a>
                    <div class="layuimini-tool"><i title="展开" class="fa fa-outdent" data-side-fold="1"></i></div>
                </a>

                <!--电脑端头部菜单-->
                <ul
                    class="layui-nav layui-layout-left layuimini-header-menu layuimini-menu-header-pc layuimini-pc-show">
                </ul>

                <!--手机端头部菜单-->
                <ul class="layui-nav layui-layout-left layuimini-header-menu layuimini-mobile-show">
                    <li class="layui-nav-item">
                        <a href="javascript:;"><i class="fa fa-list-ul"></i> 选择模块</a>
                        <dl class="layui-nav-child layuimini-menu-header-mobile">
                        </dl>
                    </li>
                </ul>

                <ul class="layui-nav layui-layout-right">

                    <li class="layui-nav-item" lay-unselect>
                        <a href="javascript:;" data-refresh="刷新"><i class="fa fa-refresh"></i></a>
                    </li>
                    <li class="layui-nav-item" lay-unselect>
                        <a href="javascript:;" data-clear="清理" class="layuimini-clear"><i class="fa fa-trash-o"></i></a>
                    </li>
                    <li class="layui-nav-item mobile layui-hide-xs" lay-unselect>
                        <a href="javascript:;" data-check-screen="full"><i class="fa fa-arrows-alt"></i></a>
                    </li>
                    <li class="layui-nav-item layuimini-setting">
                        <a href="javascript:;">
                            <?php echo $_SESSION['User'] ?>
                        </a>
                        <dl class="layui-nav-child">
                            <dd>
                                <a href="javascript:;" layuimini-content-href="page/user-changePassword.html"
                                    data-title="修改密码" data-icon="fa fa-gears">修改密码</a>
                            </dd>
                            <dd>
                                <a href="javascript:;" class="login-out">退出登录</a>
                            </dd>
                        </dl>
                    </li>
                    <li class="layui-nav-item layuimini-select-bgcolor" lay-unselect>
                        <a href="javascript:;" data-bgcolor="配色方案"><i class="fa fa-ellipsis-v"></i></a>
                    </li>
                </ul>
            </div>
        </div>

        <!--无限极左侧菜单-->
        <div class="layui-side layui-bg-black layuimini-menu-left">
        </div>

        <!--初始化加载层-->
        <div class="layuimini-loader">
            <div class="layuimini-loader-inner"></div>
        </div>

        <!--手机端遮罩层-->
        <div class="layuimini-make"></div>

        <!-- 移动导航 -->
        <div class="layuimini-site-mobile"><i class="layui-icon"></i></div>

        <div class="layui-body">

            <div class="layui-card layuimini-page-header layui-hide">
                <div class="layui-breadcrumb layuimini-page-title">
                    <a lay-href="" href="/">主页</a><span lay-separator="">/</span>
                    <a><cite>常规管理</cite></a><span lay-separator="">/</span>
                    <a><cite>系统设置</cite></a>
                </div>
            </div>

            <div class="layuimini-content-page">
            </div>

        </div>

    </div>
    <script src="lib/layui-v2.5.5/layui.js" charset="utf-8"></script>
    <script src="lib/jquery-3.7.0/jquery.js"></script>
    <script src="lib/jquery-confirm-3.3.4/jquery-confirm.min.js"></script>
    <script src="lib/jquery-toast/jquery.toast.min.js"></script>
    <script src="js/lay-config.js?v=2.0.0" charset="utf-8"></script>
    <script src="js/usermain.js"></script>
    <script>

        layui.use(['jquery', 'layer', 'miniAdmin', 'miniTongji'], function () {
            var $ = layui.jquery,
                layer = layui.layer,
                miniAdmin = layui.miniAdmin,
                miniTongji = layui.miniTongji;

            var options = {
                iniUrl: "api/init.json",    // 初始化接口
                clearUrl: "api/clear.json", // 缓存清理接口
                renderPageVersion: true,    // 初始化页面是否加版本号
                bgColorDefault: false,      // 主题默认配置
                multiModule: true,          // 是否开启多模块
                menuChildOpen: false,       // 是否默认展开菜单
                loadingTime: 0,             // 初始化加载时间
                pageAnim: true,             // 切换菜单动画
            };
            miniAdmin.render(options);

            $('.login-out').on("click", function () {
                $.confirm({
                    title: '即将退出登录',
                    content: '你真的要注销本次会话吗？',
                    icon: 'fa fa-user-times',
                    type: 'orange',
                    buttons: {
                        YES: function () {
                            $.ajax({
                                url: '/ajax.php',
                                method: 'post',
                                catch: false,
                                data: {
                                    do: 'loginout'
                                },
                                success: function () {
                                    $.alert({
                                        title: '成功',
                                        content: '成功注销',
                                        type: 'green',
                                        icon: 'fa fa-check',
                                        buttons: {
                                            OK: function () {
                                                window.location.href = '/user';
                                            }
                                        }
                                    })
                                },
                                error: function () {
                                    $.alert({
                                        title: '失败',
                                        content: '指令发送失败，请手动退出浏览器清除登录状态',
                                        type: 'red',
                                        icon: 'fa fa-exclamation-circle'
                                    })
                                }
                            })
                        },
                        NO: function () { }
                    }
                })
            });
        });
    </script>
</body>

</html>