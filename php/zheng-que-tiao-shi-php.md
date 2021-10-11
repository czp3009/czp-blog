# 正确调试 PHP

环境: Ubuntu 18.04 | PHPStorm 2018.2.3 | PHP 7.2.10

之前在 Google 搜索 `PHPStorm xdebug`, 结果搜到的中文结果里, 全是用什么 `PHPStorm` 的 Server 设置, 然后一阵眼花缭乱的配置, 说道, 调试 PHP 很简单.

实际上 `PHPStorm` 文档中已经写的很明确了, 调试 PHP 不需要任何配置 [https://www.jetbrains.com/help/phpstorm/zero-configuration-debugging.html](https://www.jetbrains.com/help/phpstorm/zero-configuration-debugging.html)

所以我们今天就来看看, 如何正确调试 `PHP`.

## 调试器

`PHP` 的调试器有好几个, 其中最有名的是 `xdebug`, 同时这也是 `PHPStorm` 推荐的调试器.

`PHP` 自身不自带调试器, 调试器是一个模块. 所以我们首先要来安装它. 执行以下命令行

```bash
apt install php-xdebug
```

没错, 这样就安装好了.

然而 `xdebug` 默认没有配置 `IDE KEY`, 所以我们还是得配置一下(说好的 zero-configuration 呢).

首先, 我们得找到 `xdebug` 的配置文件的位置, 我们使用 `PHP 探针` 来查看 `PHP` 的所有配置信息.

使用命令行

```bash
php -r "phpinfo();" | grep xdebug
```

输出的内容中, 第一行应该是这样的

```
/etc/php/7.2/cli/conf.d/20-xdebug.ini,
```

这就是 `xdebug` 的配置文件路径, 我们打开并修改它, 填入如下配置(root 权限)

```
zend_extension=xdebug.so
xdebug.idekey='PHPSTORM' 
xdebug.remote_enable=1
```

`zend_extension` 是默认就存在的配置项, 不需要修改它. `xdebug.idekey` 指定了一个字符串, 用于与 `xdebug` 建立连接(Socket)时的密钥 `xdebug.remote_enable` 由于使用的是一个 Socket 连接, 所以所有调试都是远程调试

(注意, `xdebug` 的默认端口为 9000, 不应该改变这个设置, 因为大量工具都以此为默认值)

如果配置项不存在则添加它, 最后保存这份文件.

## 使用 Built-in Server

从 `PHP 5.4` 开始, `PHP` 就提供了一个用于快速调试的内建服务器, 支持静态文件与 `PHP` 脚本, 也支持 `Symbolic Link` 与 `router.php`.

它相当的方便, 但是很多人并不知道它的存在.

首先我们在 `PHPStorm` 中创建一个使用内建服务器的 `Run Configuration`.

以 `yii2` 框架为例, 配置大致上是这样的

![yii2 run configurations](<../.gitbook/assets/image (3).png>)

就这么简单么, 没错, 就是这么简单.

之后启动这个服务端, 我们就可以看到 `yii2` 的标志性首页了.

我们迫不及待的在代码里打了一个断点, 然后刷新页面, 发现并没有在断点处中断.

因为此时并没有东西连接到 `xdebug` 上.

我们还需要一个浏览器插件.

## XDebug Helper

以 `Chrome` 为例, 需要首先安装插件 [https://chrome.google.com/extensions/detail/eadndfjplgieldjbigjakmdgkmoaaaoc](https://chrome.google.com/extensions/detail/eadndfjplgieldjbigjakmdgkmoaaaoc)

如果你使用 `FireFox`, 详见此处 [https://www.jetbrains.com/help/phpstorm/browser-debugging-extensions.html](https://www.jetbrains.com/help/phpstorm/browser-debugging-extensions.html)

插件安装后, 我们在 `Chrome` 右上角可以看到插件的图标(一个小虫子), 我们在图标上右键, 点击 `选项` 即可打开插件的设置界面.

`IDE Key` 这一项下面的下拉框中选中 `PhpStorm`, 此时 `IDE Key` 会被自动设置为 `PHPSTORM`, 这就是为什么之前我们在 `xdebug` 设置中, 需要把 `xdebug.idekey` 的设置为这个.

![](<../.gitbook/assets/image (4).png>)

之后我们打开我们的网站上的一个页面(随便哪个页面)(`HttpStatus` 必须是 `200`, 否则插件无法启用), 然后在插件的图标上点左键, 再点 `Debug`

这样, 插件就启用了对当前站点的调试.

![](<../.gitbook/assets/image (5).png>)

注意, 启用后, 插件图标应该变绿.

## 调试

然后我们再到代码中打一个断点, 刷新页面, 此时我们的代码确实在这里停下了, 并可以看到所有的变量.

![](<../.gitbook/assets/image (6).png>)

我们终于可以调试 PHP 了, 小伙伴们一把眼泪一把鼻涕地跟 echo 调试法 说再见.
