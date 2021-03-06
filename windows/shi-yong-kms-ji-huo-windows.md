# 使用 KMS 激活 Windows

在很多情况下, 出于不可抗力, 必须使用 Windows 系统, 于是如何激活就成了一个问题. 特别是在虚拟机中使用 Windows, 不可能为每个虚拟机都购买一次授权.

那么那些云服务商, 是怎么为用户激活 Windows 系统的呢, 很简单, 使用 KMS.

KMS 是微软提供的一种激活解决方案, 用于让购买了企业授权的用户能够批量激活自己公司内的计算机. 用户只需要设定 KMS 服务器为公司提供的一个特定地址, 然后输入一个 KMS 专用的固定序列号, 就可以激活 Windows.

阿里云也正是那么做的, 阿里云内网的 KMS 服务器 `kms.cloud.aliyuncs.com` \(指向 `100.100.3.8`\). 如果你有想法, 可以在阿里云机器上转发这一地址到公网.

当然, KMS 已经使用了那么多年, 自然也有第三方编写的模拟程序, 例如 [https://github.com/SystemRage/py-kms](https://github.com/SystemRage/py-kms)

使用以下命令在一台 Linux 服务器上部署模拟程序

```bash
git clone https://github.com/SystemRage/py-kms
cd py-kms/py-kms
python pykms_Server.py
```

如果有防火墙, 则必须放行 `1688` 端口.

现在我们打开需要激活的 Windows, 使用管理员权限运行 `Powershell`

```text
slmgr /upk
slmgr /skms kms.hiczp.com
```

第一条命令用于清空当前已设置的产品序列号\(如果有的话\), 第二条命令的最后一个参数是刚才部署的模拟程序地址.

接下去我们必须要知道自己的 Windows 版本, 使用如下命令来查看

```text
DISM /online /Get-CurrentEdition
```

然后到 [https://docs.microsoft.com/en-us/windows-server/get-started/kmsclientkeys](https://docs.microsoft.com/en-us/windows-server/get-started/kmsclientkeys) 找到对应的 KMS 序列号. 一部分非 Server 系统在这个页面找不到, 请到这里找 [https://gist.github.com/CHEF-KOCH/1273041f0eafd20f2219](https://gist.github.com/CHEF-KOCH/1273041f0eafd20f2219)

使用以下命令来设置对应的序列号

```text
slmgr /ipk N69G4-B89J2-4G8F4-WWYCC-J464C
```

立即连接 KMS 服务器并激活

```text
slmgr /ato
```

还可以使用命令来查看激活状态

```text
slmgr /dli
```

如果使用的是评估版本 Windows Server, 则必须在清除原密钥并设定 KMS 服务器后首先进行版本转换, 使用以下命令来查看可以转换至的版本

```text
DISM /online /Get-TargetEditions
```

例如可以转换至 `ServerStandard` 或 `ServerDatacenter`.

选择一个想要转换至的版本, 然后在刚才的页面上查找得到目标版本的 KMS 序列号, 键入如下命令

```text
DISM /Online /Set-Edition:ServerStandard /ProductKey:N69G4-B89J2-4G8F4-WWYCC-J464C /AcceptEula
```

`Set-Edition` 的值为想要转换至的版本, `ProductKey` 为想要转换至的版本对应的 KMS 序列号.

转换完成后将提示重启计算机, 输入 `y` 立即重启. 重启之后将自动连接 KMS 服务器进行激活, 如果没有则手动使用命令

```text
slmgr /ato
```

这样, Windows 就激活了.

