# 挖掘门罗币

Monero\(中文名 门罗币, 缩写 XMR\) 是一种故意设计成只有通用计算芯片才能挖掘的币, 目的是对抗 ASIC 制造商以阻止少部分人垄断大部分算力.与其他大多数用显卡才能有效挖掘的加密货币不同, XMR 只有用 CPU 才能有效挖掘.

挖掘门罗币推荐使用 xmrig 挖矿程序 [https://github.com/xmrig/xmrig](https://github.com/xmrig/xmrig)

在开始挖矿前还需要准备 XMR 钱包并选择一个矿池.

XMR 钱包可以在这个页面下载 [https://www.getmonero.org/downloads](https://www.getmonero.org/downloads/). 或者直接使用交易所提供的充值地址\(那是交易所为用户自动生成的钱包\), 这样就不需要额外使用钱包软件, 挖矿所得也可以直接在交易所卖出, 十分方便. 注册交易所详见另一篇文章 [加密货币交易](jia-mi-huo-bi-jiao-yi.md).

支持 XMR 的矿池比较知名的有 [nanopool](https://xmr.nanopool.org), [minexmr](https://minexmr.com), [minergat](https://minergate.com). 其中 minexmr 算力占了全网大约三分之一, 这是本文推荐的矿池.

挖掘门罗币只需要使用 CPU, 不需要别的硬件驱动程序, 选择一个合适的矿池服务器就可以开始挖了 [https://minexmr.com/miningguide](https://minexmr.com/miningguide)

命令行示例

Linux

```bash
./xmrig --tls -o sg.minexmr.com:443 -u 89HTtHCpsU7SEUgWpmGqfP6LfxLcE9ViHJDPVKjqnoQzTpkknAskdz2h4SRm42GRRAJdWZyUSS6W5LCxUCwqq3ys1EhRr3R --rig-id=home --randomx-1gb-pages
```

Windows

```text
xmrig.exe --tls -o sg.minexmr.com:443 -u 89HTtHCpsU7SEUgWpmGqfP6LfxLcE9ViHJDPVKjqnoQzTpkknAskdz2h4SRm42GRRAJdWZyUSS6W5LCxUCwqq3ys1EhRr3R --rig-id=home
```

\(更多命令行参数详见 [https://xmrig.com/docs/miner/command-line-options](https://xmrig.com/docs/miner/command-line-options)\)

当执行者有 root/Administrator 权限时, 软件将自动启用额外的内核模块使挖矿速度更快.

**钱包地址请换成自己的**, 当然我不介意大家帮我挖矿.

`--rig-id` 的值表示矿机名, 只用来区分算力来源, 没有实际作用, 可以是任意字符串.

Linux 平台可使用 `--randomx-1gb-pages` 参数来额外提高挖矿速度.

不建议使用非 tls 端口, 在国内有几率连不上.

挖矿开始后在矿池提供的面板页面 [https://minexmr.com/dashboard](https://minexmr.com/dashboard) 输入自己的钱包地址就可以看到自己的挖矿状态. 页面上的加号减号用来修改自动提现的阈值, 每次提现都会被 XMR 网络收取 0.0004 XMR 手续费. 'Send Now' 按钮用于手动提现\(最小值 0.004\), 按一下就会把已挖到的币送入自己的钱包.

xmrig 会在网络不幸断开时自动重连, 可以无人值守源源不断产出 XMR.

XMR 挖掘收益并不高, 在目前\(2021 年 3 月\)一块 AMD 3700X\(Ubuntu 20.04\) 一天只能产出大约 0.8 美元的挖矿成果, 约等于一瓶可乐.

祝大家挖矿愉快.

