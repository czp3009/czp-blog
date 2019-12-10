# 为 KVM 中的 Windows 虚拟机启用 VirtIO

众所周知, KVM 全称 Kernel-based Virtual Machine, 那么 Windows 虚拟机在其上的表现一定稀烂, 尤其是硬盘和网络 IO. 而 VirtIO 是为 KVM 所设计的, 用于 IO 设备的半虚拟化驱动, 可以让虚拟机以逼近宿主机的速度来使用 IO 设备.

以 qemu-kvm 为例, 在 virt-manager 创建好一个新的虚拟机, 如果是 Linux 系统\(内核版本 2.6 以上\), 那么磁盘总线将默认使用 VirtIO. 但是如果是 Windows, 那么磁盘总线将默认为 IDE, 而且如果修改为 VirtIO, 将识别不了硬盘. 这说明我们必须为 Windows 虚拟机进行额外操作, 来让他支持 VirtIO.

对于已经安装完成了的 Windows 虚拟机, 最方便的办法是使用 `choco`  来安装 VirtIO 驱动.

首先访问 [https://chocolatey.org/](https://chocolatey.org/) 并复制黏贴 GetStarted 中的 powershell 命令来安装 choco, 随后使用它来安装 VirtIO 驱动

```text
choco install virtio-drivers
```

安装结束后关闭虚拟机, 并在虚拟机设置中将磁盘和网络都调为 VirtIO

![](../.gitbook/assets/image%20%2824%29.png)

![](../.gitbook/assets/image%20%2813%29.png)

保存设置并启动虚拟机.

在某些版本的 Windows 中, 此时可能产生启动失败\(进入了 Windows 修复工具\). 如果碰到这种情况, 先将磁盘总线调回 IDE\(网络应该不需要调回去\)然后挂载一个新的虚拟磁盘, 总线为 VirtIO. 开机之后进入设备管理, 应该能看到第二块磁盘的名字叫做 Red Hat VirtIO SCSI driver, 并且驱动也在正常工作. 如果在磁盘管理器中尝试对这块磁盘进行初始化, 这块磁盘将能够正常存取数据. 那么为什么系统盘不能使用 VirtIO 呢, 别急, 先把虚拟机关掉, 把系统盘调为 VirtIO, 然后把之前加的临时盘删掉, 再重启, 它就莫名其妙的可以开机了.

还有一种使用 VirtIO 的方案是在安装虚拟机时就为 Windows 安装驱动.

首先我们要先下载 VirtIO 驱动文件 [https://www.linux-kvm.org/page/WindowsGuestDrivers/Download\_Drivers](https://www.linux-kvm.org/page/WindowsGuestDrivers/Download_Drivers)

如果母鸡是 Debian/Ubuntu 系的, 将指引到这个页面 [https://launchpad.net/kvm-guest-drivers-windows/+download](https://launchpad.net/kvm-guest-drivers-windows/+download)

通常来说只需要下载最新版本驱动的 ISO 格式的光盘镜像文件. 很老的 Windows 可能无法正常读取 qemu 虚拟的光驱设备, 此时需要下载 VFD 格式的软盘镜像文件.

然后我们开始安装虚拟机, 在安装前自定义配置.

注意此时就要将磁盘和网络都调为 VirtIO

![](../.gitbook/assets/image%20%287%29.png)

另外再添加一个光驱, 指向刚才下载的驱动镜像.

![](../.gitbook/assets/image%20%2817%29.png)

引导顺序不需要改动, virt-manager 会帮我们保持正确的顺序.

安装的时候会无法识别磁盘

![](../.gitbook/assets/image%20%2823%29.png)

点击 `加载驱动程序` , 此时可能会出现

![](../.gitbook/assets/image%20%284%29.png)

无视这个提示, 然后点击左下角的 `浏览`  并选择一个驱动所在的目录\(Windows 2008 之后的驱动全都是通用的\).

![](../.gitbook/assets/image%20%2814%29.png)

![](../.gitbook/assets/image%20%2818%29.png)

然后我们就可以看到磁盘驱动器了.

![](../.gitbook/assets/image.png)

对于网络驱动也是同样的操作来安装.

驱动镜像中的 `Ballon` 用于宿主机可以动态的为客户机增加内存, `Serial` 用于复用 qemu 控制台, 作用不是很大, 而且这两个驱动的签名应该是过期了. 如果想了解更多关于 Windows 驱动签名的问题请查看此处 [https://docs.microsoft.com/en-us/previous-versions/windows/hardware/design/dn653559\(v=vs.85\)](https://docs.microsoft.com/en-us/previous-versions/windows/hardware/design/dn653559%28v=vs.85%29)

完成以上操作后, 安装完成的 Windows 虚拟机就有了 VirtIO 支持.

![](../.gitbook/assets/image%20%289%29.png)

而至于为什么任务管理器里看不到磁盘性能, 我只知道, 阿里云的机器上也看不到.

