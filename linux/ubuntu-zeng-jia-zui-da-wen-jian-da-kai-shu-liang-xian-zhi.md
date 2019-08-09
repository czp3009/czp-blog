# Ubuntu 增加最大文件打开数量限制

环境: Ubuntu Desktop 18.04

众所周知, Linux 的文件打开数量限制是用 `ulimit -n` 命令查看的.

```text
$ ulimit -n 
1024
```

然后我们就会发现默认只有 1024.

然后我们按照人云亦云的办法, 去修改 `/etc/security/limits.conf`, 在文件末尾增加如下两行

* hard nofile 65535
* soft nofile 65535

不要说用 `sysctl -p` 了, 即使我们重启计算机, 我们的 `ulimit -n` 也不会改变.

那么这是为什么呢, 因为我们作为开发机的这台机器, 是装有图形界面的, 我们是以图形用户身份登陆的.

而 `Ubuntu` 自从使用了 `systemd` 之后, 图形用户的启动管理是由 `systemd` 施行的, 所以修改这个文件只修改了命令行用户的 `ulimit -n`.

我们用自己的账号登陆为一个命令行用户来看一下命令行用户的最大文件打开数有没有改变

```text
$ su {yourUsername}
$ ulimit -n
65535
```

没错, 命令行用户的 `ulimit -n` 确实改变了.

那么怎么改变图形用户的文件打开数限制在哪里改呢.

在这个文件 `/etc/systemd/system.conf`

我们找到其中的一行

```text
#DefaultLimitNOFILE=
```

去掉 `#` 号然后写上值

```text
DefaultLimitNOFILE=65535
```

不清楚这个文件怎么热重载, 所以重启计算机吧.



