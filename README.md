# PCA - 云端API系统

这是一个有基础的**PHP作品授权管理**的API系统，可以保护你的PHP作品不被非法破解，并且能从云端获取关键数据进行防止你的机密数据被破解，不必担心传输过程，PCA会帮你解决这些问题，采用强加密算法，极难破解加密数据。  

## 优缺点  
- 优点 
1. 强加密数据传输有效保护数据安全，难破解，三重技术加密。  
2. 多APP管理，可控版本开关，设置版本可用性、升级包、安装包。
3. 云端更新作品，免去繁琐的更新步骤。按照规范制作升级包，PCA会帮你升级本地文件和数据库。
4. 强大授权管理系统，自定义授权码可用性、有效时间、过期时间，查看心跳时间、访问次数、上次版本等。
5. 远程变量功能，通过PCA类方法获取你的云端数据有效防止数据泄露，可以随时修改关键数据维护系统运行。
6. 请求转发，通过PCA云服务器转发你的http请求，将请求配置在PCA云端携带请求的身份ID可以直接通过PCA转发你的请求，如果方式是POST还可以转发你的POST数据。有效防止你的核心服务器遭到攻击或被滥用。可以选择加密转发和明文转发。
7. 随时可控的软件可用性、版本可用性、作者API访问开关。随时禁用你的APP。
- 缺点
1. 强加密传输费时，延迟高（200ms+）
2. 项目开发未完成，部分功能体验不佳。
3. 所有前端交互功能API集中于ajax.php内，所有API接口集中于api.php内，可能会造成一个功能错误影响N+功能。

## 特别鸣谢
**layuimini**后台开发模板

## 环境依赖
PHP7.4
MySQL5.6

## 安装方法
以后再说(悲)

## 作者心语
这是一个半成品，目前所用的功能属于用户端，后台端还未开始安排任务。应该没有人用别人的API系统吧？？？  
如果有Bug，心中默念：正常正常 消气消气  
如果有任何问题 Github上留言  
喜欢可以点Star  