# 挖掘以太坊

警告: 截止目前 2022 年 9 月, eth 已转向 POS, 不能再挖矿了

Ethereum(中文名 以太坊, 缩写 ETH)使用 Ethash 算法来挖掘, 这种算法是一种内存密集型算法, 内存吞吐量越高计算越快. 显卡所用的显存比需要通过数据总线来传输数据的主板内存快得多, 因此以太坊主要使用显卡来挖掘, CPU 挖掘的速度低至忽略不计.

Ethash 算法会把已有的区块的加密信息放在显存中继而计算下一区块. 由于以太坊支持 uncle block, 因此这些数据甚至不是链而是有向无环图(DAG). 这个 DAG 会以每年大约 520MiB 的速度增大, 在目前(2021 年 3 月)已经达到了 4.2GiB, **如果你的显卡显存没有超过 4.2GiB 将挖不了以太坊**.

DAG 占用的内存每年逐渐增大, 因此 ASIC 矿机挖掘以太坊的效益并不是那么高. 通常 ASIC 的内存都不是那么快(相比显卡所用的 DDR6), 并且没有那么大, 所以矿老板并不是非常乐意挖以太坊, 这也就意味着业余加密货币爱好者用自己的显卡挖以太坊也可以有相对比较好的收益(相较于比特币).

有不少 ETH 挖矿程序并不开源, 无法得知其内部是否有恶意代码窃取用户电脑上的数据, 业余加密货币爱好者一般不会有多台高性能电脑, 挖矿用的电脑就是平时所用的电脑, 因此使用一款安全的挖矿程序格外重要. 甚至有很多闭源程序或补丁会标榜自己能够 "优化" 算法, 使相同的硬件运行得到更高的 HashRate, 实际上 Ethash 核心算法是开源的, 而且早已优化到不能再优化, 那些程序只不过是给使用者的显存超频而已. 显存超频之后会提高硬件出错率, 加速元件老化, 也会导致原本风道设计合理的机箱变得无法及时散热进一步损害其他硬件的健康(矿老板正是因为超频挖矿所以矿卡故障率很高).对于业余加密货币爱好者来说, 为了体验挖矿的乐趣而损害计算机健康得不偿失.

## 挖矿程序

挖掘以太坊推荐使用 ethminer 挖矿程序 [https://github.com/ethereum-mining/ethminer](https://github.com/ethereum-mining/ethminer)

在 release 页面下载适合自己所用的操作系统的最新版程序, 例如 "ethminer-0.19.0-alpha.0-cuda-9-linux-x86\_64.tar.gz" 表示适用于 Linux 操作系统的用 CUDA9 编译的 0.19.0 版本.

下载完挖矿程序之后, 还需要安装对应版本的 CUDA. 不过安装显卡驱动时可能已经自带了 CUDA, 使用以下命令来查看显卡和显卡驱动器状态

```powershell
nvidia-smi
```

如果显示已经安装了 CUDA 就不需要另外安装了. 值得一提的是, 软件是面向旧版本 CUDA 编译的, 因为作者称新版本 CUDA 的挖矿效率没有那么高, 但是安装了新版本 CUDA(例如 CUDA11) 也是能运行软件(CUDA 9/10)的, 如果追求最高的性能可以删除安装驱动时自动安装的 CUDA 再手动安装旧版本.

Windows 系统也是用这个命令来查看, 但是通过 Windows 更新安装的驱动有可能没有这个命令, 可以先尝试运行一下挖矿程序, 运行报错则再手动安装 CUDA.

如果安装驱动时没有自动安装 CUDA, 请到 NVIDIA 官网下载 [https://developer.nvidia.com/zh-cn/cuda-downloads](https://developer.nvidia.com/zh-cn/cuda-downloads)

## 以太坊钱包

在开始挖掘之前要先拥有一个自己的以太坊钱包.

以太坊钱包软件有很多, 与其他加密货币钱包一样, 不开源==送钱给别人. 在这个页面可以找到可靠的钱包软件 [https://ethereum.org/en/wallets/find-wallet/](https://ethereum.org/en/wallets/find-wallet/) 挑选其中一款你觉得好看的钱包软件就可以开始了. 或者直接使用交易所提供的充值地址(那是交易所为用户自动生成的钱包), 这样就不需要额外使用钱包软件, 挖矿所得也可以直接在交易所卖出, 十分方便. 注册交易所详见另一篇文章 [加密货币交易](jia-mi-huo-bi-jiao-yi.md).

## 选择矿池

以太坊矿池也有很多, 在一些矿池列表网站上可以找到它们 [https://investoon.com/mining\_pools/eth](https://investoon.com/mining\_pools/eth)

目前第一名的矿池(按算力排序)是 [SparkPool](https://www.sparkpool.com), 这个矿池的网站很多页面不登录不给看, 对于想要匿名挖矿的用户请直接跳过这一矿池. 第二名的矿池是 [Ethermine](https://ethermine.org), 与其他国外矿池一样, 它支持匿名挖矿, 并不需要任何个人资料也不需要任何形式的注册, 这是本文推荐的矿池.

如果你已经注册了加密货币交易所, 并且交易所自己开设了矿池, 可以直接用交易所的矿池, 比如[币安矿池](https://pool.binance.com). 交易所提供的矿池一般没有最小提现额度限制, 挖出多少就可以立即在市场上交易多少.

由于众所周知的原因, 主流矿池在大陆地区均无法访问, 使用前需先配置科学上网.

### Ethermine

打开矿池的网站查看矿池提供的服务器 [https://ethermine.org/start](https://ethermine.org/start)

然后用以下命令连接适合的服务器开始挖矿

Linux

```bash
./ethminer -P stratum1+ssl://0xd39eecf6fd2d47a2955dd50befb1dbd7e457e9dd.home@asia1.ethermine.org:5555
```

Windows

```powershell
ethminer.exe -P stratum1+ssl://0xd39eecf6fd2d47a2955dd50befb1dbd7e457e9dd.home@asia1.ethermine.org:5555
```

(更多命令行样例详见 [https://github.com/ethereum-mining/ethminer/blob/master/docs/POOL\_EXAMPLES\_ETH.md](https://github.com/ethereum-mining/ethminer/blob/master/docs/POOL\_EXAMPLES\_ETH.md))

"0x" 开头的字符串是以太坊钱包地址, 钱包地址后方的 . 后面的 "home" 表示矿机名, 可以是任何字符串, 仅用于统计算力时区分算力来源, 没有实际作用.

**钱包地址请换成自己的**, 当然我不介意大家帮我挖矿.

请不要使用非 SSL 协议和端口连接矿池服务器, 在国内有几率连不上.

挖矿开始后, 会花一段时间来生成 DAG, 通常二十秒内可以完成. 当 HashRate 不为 0 时说明开始挖矿了, 例如

```
 m 12:02:38 ethminer 12:35 A248:R1 35.12 Mh - cu0 35.12
```

在矿池的网站的首页([https://ethermine.org](https://ethermine.org))输入自己的钱包地址就可以查看自己的挖矿状态. 网站数据更新有延迟, 建议挖了一个小时再去看. 在页面上也可以更改提现阈值(0.1 ETH 到 10 ETH), 旁边有详细的支付说明.

### 币安矿池

首先创建自己的挖矿账户 [https://pool.binance.com](https://pool.binance.com), 如果还没有注册过币安请先[注册](https://www.binance.com/zh-CN/register?ref=88039964).

在币安矿池挖矿不使用以太坊钱包地址, 而使用币安挖矿账户名.

币安矿池参数示例

```powershell
ethminer.exe -P stratum+tcp://czp3009.home@ethash.poolbinance.com:443
```

**挖矿账户名请换成自己的**.

币安矿池的收益每天发放一次, 请第二天再看. 矿池收益无论多少都可以转到现货账户.

币安现货交易最低金额 10 USDT, 相当于"提现"金额为 10 美元, 这远低于各大矿池的提现额度, 可以很快把挖矿所得变现.

### 注意事项

截止 2022 年, 随着加密货币挖矿在大陆地区不再合法, 大多数矿池都因为某些众所周知的原因而无法正常连接, 必须通过科学上网才能访问. 另, 由于其中也伴随着 DNS 污染, 所以必须使用远程 DNS 解析.

## FAQ

ethminer 这个软件有一个毛病, 在网络不幸断开时不会自动重连而是结束运行, 如果你的挖矿电脑无人值守, 请在命令行写一个死循环来执行它.

## 总结

稍微好一些的显卡一天能挖四五个美元, 收益超过电费, 所以业余数字货币爱好者挖以太坊是合算的. Ethash 是内存密集型算法, 并不需要特别高的核心频率, 在机箱风道合理的情况下显卡温度(以 GTX 1080/1080 TI作为例子)也不会很高(低于 65°), 这甚至远远低于玩大型游戏时的温度, 只要不超频就不会对显卡带来损害, 甚至还可以一边挖矿一边打游戏.

祝大家早日发家致富, 然后分我一点.
