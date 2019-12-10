# 为 KVM 中的 Windows 虚拟机启用 VirtIO

众所周知, KVM 全称 Kernel-based Virtual Machine, 很显然这是为 Linux 设计的, 那么 Windows 客户机在其上的表现果不其然非常稀烂, 尤其是硬盘 IO 性能低至不可忍受\(当使用 qcow2 时\). 这时候就必须要用 VirtIO, 它是一套为半虚拟化设计的通过一组标准化的 API 来实现在多种不同的虚拟化方案中复用专用的客户机驱动代码的解决方案.

如果客户机是 Linux 系统, 那么只要内核版本高于 2.6, 而且内核编译时开启了 libvirt 支持, 那么直接就支持 VirtIO, 几乎所有发行版都是如此. 在 virt-manager 创建新的虚拟机时, 将为 Linux 系统的客户机直接启用 VirtIO. 但是 Windows 并不自带对 VirtIO 的支持, 所以我们必须想办法为 Windows 系统安装对应的驱动.

首先我们要先下载 VirtIO 驱动文件 [https://www.linux-kvm.org/page/WindowsGuestDrivers/Download\_Drivers](https://www.linux-kvm.org/page/WindowsGuestDrivers/Download_Drivers)

直接可用的二进制文件见此 [https://docs.fedoraproject.org/en-US/quick-docs/creating-windows-virtual-machines-using-virtio-drivers/index.html\#virtio-win-direct-downloads](https://docs.fedoraproject.org/en-US/quick-docs/creating-windows-virtual-machines-using-virtio-drivers/index.html#virtio-win-direct-downloads)

通常来说只需要下载 ISO 格式的光盘镜像文件. 较老的 Windows 可能无法正常读取 qemu 虚拟的光驱设备, 此时需要下载 VFD 格式的软盘镜像文件.

光盘镜像中的各个文件夹都对应某种驱动, 在下载页面有详细描述, 我在此复制黏贴一遍

```text
NetKVM/ - Virtio network driver

viostor/ - Virtio block driver

vioscsi/ - Virtio Small Computer System Interface (SCSI) driver

viorng/ - Virtio RNG driver

vioser/ - Virtio serial driver

Balloon/ - Virtio memory balloon driver

qxl/ - QXL graphics driver for Windows 7 and earlier. (build virtio-win-0.1.103-1 and later)

qxldod/ - QXL graphics driver for Windows 8 and later. (build virtio-win-0.1.103-2 and later)

pvpanic/ - QEMU pvpanic device driver (build virtio-win-0.1.103-2 and later)

guest-agent/ - QEMU Guest Agent 32bit and 64bit MSI installers

qemupciserial/ - QEMU PCI serial device driver

*.vfd VFD floppy images for using during install of Windows XP
```

然后我们开始安装虚拟机, 在安装前自定义配置.

注意此时就要将**磁盘和网络**都调为 VirtIO

![](../.gitbook/assets/image%20%288%29.png)

另外再添加一个光驱, 指向刚才下载的驱动镜像.

![](../.gitbook/assets/image%20%2832%29.png)

然后开始安装.

安装的时候会无法识别磁盘

![](../.gitbook/assets/image%20%2824%29.png)

点击 `加载驱动程序` , 然后点击 `浏览` , 根据你的操作系统版本选择对应的文件夹

![](../.gitbook/assets/image%20%285%29.png)

![](../.gitbook/assets/image%20%2830%29.png)

安装完毕之后, 我们现在可以看到磁盘了

![](../.gitbook/assets/image%20%2833%29.png)

对于网络驱动也是一样的操作. 除了硬盘和网络驱动之外都是可选的, 根据实际需要来安装. 这里提供阿里云预装的 VirtIO 驱动清单: `viostor` `netkvm` `balloon` `pvpanic` `vioser` . 至于每个驱动都有什么用, 在驱动下载页有描述.

安装完所需的驱动之后就可以开心的安装系统啦.

![](../.gitbook/assets/image%20%2836%29.png)

如果系统已经装好, 才想到要安装 VirtIO, 此时可以先关闭虚拟机, 挂载一个 VirtIO 磁盘总线的额外虚拟磁盘, 然后开机. 到设备管理器中找到这个设备, 为他安装驱动\(在所挂载的光盘镜像中选择\).

现在, Windows 客户机的磁盘性应该能达到能用的程度了.

