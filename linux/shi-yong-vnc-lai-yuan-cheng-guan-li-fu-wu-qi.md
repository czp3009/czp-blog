# 使用 VNC 来远程管理服务器

众所周知, VNC 可以让用户远程访问服务器的图形界面, 这对于一些工作站来说是非常棒的功能. 但是网上能搜索到的资料可谓是讲不清道不楚, 现在我来教你如何最简单的使用 VNC.

首先, 登陆到需要被远程管理的服务器上, 安装 VNC 服务端

```bash
sudo apt install vnc4server
```

注意, 他不是一个服务, 不会长期运行, 接下去会讲到.

如果以 `root` 账户来安装软件包, 此时务必记得退出到普通账户, 桌面环境所用的账户取决于启动 VNC 服务器的账户.

此时启动服务端

```bash
vncserver
```

如果是第一次启动, 将要求配置一个密码, 务必记住它.

随后将会打印出这么几行内容

```text
New 'server_name:1 (czp)' desktop is server_name:1

Starting applications specified in /home/czp/.vnc/xstartup
Log file is /home/czp/.vnc/server_name:1.log
```

这时候兴冲冲的连接上去, 就会看到一个灰色的屏幕, 那当然是因为我们还没有安装图形界面.

我们先把已启动的服务端关闭

```bash
vncserver -kill :1
```

其中的 `1`  是服务端启动时打印出来的序号, 更在服务器名后面.

很多人对服务端安装什么桌面环境犹豫不决, 秉承 GNU 一贯的实用主义风格, 服务器应当选择一种占用资源尽可能少的桌面, 因此我推荐 `xfce4`

```bash
sudo apt install xfce4
```

然后我们要对 VNC 服务端进行配置, 让它在启动时开启对应的桌面. 还记得第一次启动服务端时打印出的配置文件路径么, 编辑它

```bash
vim /home/czp/.vnc/xstartup
```

通常来说, 默认配置已经非常完整, 只需要在最后一行加入\(注意最后一个 &\)

```text
startxfce4 &
```

如果你的 vnc4server 版本比较旧, 默认配置不完整, 可以参考以下

```bash
#!/bin/sh

# Uncomment the following two lines for normal desktop:
# unset SESSION_MANAGER
# exec /etc/X11/xinit/xinitrc

[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
xsetroot -solid grey
vncconfig -iconic &
x-terminal-emulator -geometry 80x24+10+10 -ls -title "$VNCDESKTOP Desktop" &
x-window-manager &
startxfce4 &
```

其中的 `xrdb $HOME/.Xresources` 和 `startxfce4 &` 是必须的.

然后再次启动 VNC 服务端.

那么如何连接上服务端呢. 首先我们需要一个 VNC 客户端程序. 如果你在本地使用 Ubuntu, 那么系统已经自带了 `Remmina` 并且应该已经预装了 VNC 插件. 连接配置也非常简单

![](../.gitbook/assets/image%20%2817%29.png)

服务器应当填写 `服务器地址:桌面序号`

![](../.gitbook/assets/image%20%2855%29.png)

如果想要长期使用 VNC, 可以将 `vnc4server` 写成服务并使用 `systemctl` 来管理.

