# Ubuntu 下使用 zsh

环境: Ubuntu 18.04 \| zsh 5.4.2

久闻 `zsh` 大名, 但是一直没有用过, 因为听说配置起来很麻烦.

直到最近听说了 [oh-my-zsh](https://github.com/robbyrussell/oh-my-zsh) \(早就有了\), 据称它可以几乎零配置, 所以我们今天就来试一试它.

## 安装 zsh

`oh-my-zsh` 只是 `zsh` 的自动配置脚本, 我们得首先安装 `zsh`.

在 Ubuntu 上, 我们可以使用包管理器来简单的安装它

```bash
apt install zsh
```

之后我们开始安装 `oh-my-zsh`

## 安装 oh-my-zsh

安装 `oh-my-zsh` 可谓是出奇的简单, 但是先要安装 `git`, 很多 Linux 发行版并不会自带 `git`.

在 `Ubuntu` 上我们执行以下命令行来安装 `git`\(root 用户\)

```bash
apt install git
```

之后安装 `oh-my-zsh`\(普通用户\)

```bash
wget https://github.com/robbyrussell/oh-my-zsh/raw/master/tools/install.sh -O - | zsh
```

没错, 这就安装好了. 也可以用 `curl` 来下载脚本, 详见 [https://github.com/robbyrussell/oh-my-zsh\#basic-installation](https://github.com/robbyrussell/oh-my-zsh#basic-installation)

然后设置 `zsh` 为默认 shell

```bash
chsh -s `which zsh`
```

可能需要重启设备.

## 更换主题

一打开终端, 我们发现, 好像跟 `bash` 看起来没有什么区别, 依然很丑, 没有网上看到的别人的终端那么酷炫.

其实, 别人只是设置了一个主题而已.

`oh-my-zsh` 自带了很多个主题, 看预览图的话详见此处 [https://github.com/robbyrussell/oh-my-zsh/wiki/themes](https://github.com/robbyrussell/oh-my-zsh/wiki/themes)

那么, 怎么换主题呢, 我们以 `agnoster` 为例.

我们打开用户目录的 `~/.zshrc` 文件, 然后我们搜索 `ZSH_THEME`, 修改主题设置

```text
ZSH_THEME="agnoster"
```

使用命令来立即重载配置

```bash
source ~/.zshrc
```

然后我们会看到这么一个景象

![zsh &#x5B57;&#x4F53;&#x9519;&#x8BEF;](../.gitbook/assets/image%20%2822%29.png)

预览图中, 这个主题的向右箭头, 现在都变成了一个不可读字符.

然后我们在 Google 搜索这个问题, 很快就知道了问题所在.

原因是因为缺少 `Powerline` 字体.

## 安装 Powerline 字体

既然如此, 我们就要来安装这个字体\(字体系列\)

使用命令

```bash
git clone https://github.com/powerline/fonts
cd fonts
./install.sh
```

就安装好了.

然后我们修改终端的字体设置.

![ubuntu &#x7EC8;&#x7AEF;&#x9996;&#x9009;&#x9879;](../.gitbook/assets/image%20%2852%29.png)

很好, 我们的终端看上去十分漂亮了.

## 插件

有时候, 我们看到别人的 `zsh` 是这样的

![zsh incr &#x63D2;&#x4EF6;&#x8865;&#x5168;&#x6548;&#x679C;](../.gitbook/assets/image%20%286%29.png)

命令还没输入完, `zsh` 就自动使用唯一可能的候选项充填了光标后的部分, 使得我们不需要不停的按 `tab` 来确认确实没有其他的以这几个字符开头的可能候选项.

但是这个功能并不是 `zsh` 自带的, 而是一个插件.

这个插件叫做 `incr`, 来自 [https://mimosa-pudica.net/zsh-incremental.html](https://mimosa-pudica.net/zsh-incremental.html)

他的效果图是这样的

![](https://mimosa-pudica.net/img/zsh.gif)

我们先下载它 [https://mimosa-pudica.net/src/incr-0.2.zsh](https://mimosa-pudica.net/src/incr-0.2.zsh)

然后我们将其改名并放入正确的目录

```bash
~/.oh-my-zsh/custom/plugins/incr/incr.plugin.zsh
```

修改 `zsh` 配置, 打开 `~/.zshrc`

找到 `plugins=` 这一行

默认应该只有 `git` 插件被启用了, 我们将他改为

```text
plugins=(
  git
  incr
)
```

然后重载配置.

```bash
source ~/.zshrc
```

现在, 我们的终端, 也非常好看非常好用了. 小伙伴们欢呼雀跃!

