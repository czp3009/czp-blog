# Linux 下无限试用 JetBrains

**致命警告: 目前的 JetBrains IDEA 2021.2.3 版本(2021-10 月)需要联网并登录账号才能试用, 试用信息保存在账号内, 本文已失效.**

严重警告: 本文仅阐述技术上的可行性, 请勿使用此方案白嫖鸡巴公司

撰写此文章时的 JetBrains IDEA 版本: 2020.2.3

关于无限使用鸡巴公司在搜索引擎上搜一搜就有很多结果, 但是都是 Windows 版本的, 并且还涉及到修改注册表. 但是 Linux 没有注册表, 出于好奇特地研究了一下鸡巴公司到底是凭什么条件来判断试用过期的.

网上有一种说法是鸡巴公司会上传计算机硬件信息(例如主板序列号等)来判断此计算机是否已经使用过. 但是非常显而易见的是这种说法是站不住脚的, 因为系统重装后可以重新试用. 所以鸡巴公司的试用应该是简单的通过某个文件来记录试用信息, 通过删除文件来继续试用的思路一定是正确的.

接下去是断网测试, 试验的结果是即使关闭系统的网络连接, 鸡巴公司也会在开启后几分钟内提示试用过期, 由此可以看出鸡巴公司的试用判断是单机的.

既然是单机的那就没有魔法了. 首先是人尽皆知的保存鸡巴公司 IDE 配置的地方

```bash
~/.config/JetBrains
```

这个文件夹下会看到各个 IDE 的名字, 每个 IDE 的文件夹下都有 `eval` 文件夹, 那里面只有一个文件, 这个文件夹只有点了试用才会产生. 我们先把这个文件删掉再重新打开鸡巴公司.

这时鸡巴公司会再次回到没有证书的状态, 再一次点击试用就会重新获得三十天试用时长. 然而, 鸡巴公司在开启后几分钟就会提示试用到期, 而此时的试用剩余时间确实是三十天.

在 Windows 版本的无限使用教程中, 除了以上这个文件需要被删除, 还需要改动一个注册表 Key, 据说在 `HKEY_CURRENT_USER\SOFTWARE\JavaSoft\Prefs\jetbrains\idea`. 很显然的, 鸡巴公司在 Linux 平台上一定把这一机制使用注册表外的形式实现了. 必须找到对应的替代机制才能成功重置试用.

通过搜索大法, 很容易就可以找到另一处可疑的名为 jetbrains 的文件夹位置

```bash
~/.java/.userPrefs
```

里面会有一个 `jetbrains` , 在这个文件夹下也能看到各个 IDE 的名字, 如果旁边还有个叫 `google` 的, 那是 AndroidStudio 的文件. 每个 IDE 的文件夹打开之后, 会有一个十六进制数命名的子文件夹, 这个十六进制数其实代表用户名, 如果使用过多个鸡巴账户就会有多个这样的文件夹. 再深入进去就可以看到 `evlsprt` ,`evlsprt2` ,`evlsprt3` 几个文件夹.

这三个文件夹每个里面都会有一个 `prefs.xml` 文件. 它们打开之后差不多是这样的

```xml
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE map SYSTEM "http://java.sun.com/dtd/preferences.dtd">
<map MAP_XML_VERSION="1.0">
  <entry key="202" value="-15"/>
</map>
```

这一套 Key-Value 存储是不是非常"注册表". 删除里面的 entry 并保存, 或者直接删除整个文件.

而 evaluation support 不仅在这里有记录, 还有一个地方也有对应记录, 通过查找文件内容可以找到这个文件

```bash
~/.config/JetBrains/IntelliJIdea2020.3/options/other.xml
```

找到其中包含 `evlsprt` 字符串的元素

```xml
<property name="evlsprt3.203" value="17" />
```

它的 name 结构就是 "evlsprt${试用类型}.${IDE大版本号}", 这与刚才在 `.userPrefs` 里删掉的 `prefs.xml` 是对应的, 这里有一条就说明在那边就有一个对应文件夹下的 prefs 文件(其内容的生成算法不明).

删除所有这些包含 `evlsprt` 字符串的元素然后保存. 此时再打开鸡巴公司就可以继续试用了.

总结为以下命令

```bash
rm ~/.config/JetBrains/*/eval/*
rm ~/.java/.userPrefs/jetbrains/*/*/evlsprt*/prefs.xml
for file in ~/.config/JetBrains/*/options/other.xml; do
    grep -v evlsprt $file > ${file}_new
    mv ${file}_new $file
done
```

再次强调, 本文只旨在对鸡巴公司的试用机制进行研究, 请勿使用此法进行白嫖.
