# 将 Minecraft 服务端编译到 native

本文撰写时的 Minecraft 版本: 1.19.2, graalvm 版本: java17-22.2.0

很久以前见到过有人在 spigot 论坛过将 spigot 服务端用 [graalvm native-image](https://www.graalvm.org/22.2/docs/getting-started/#native-image) 编译的可能性: [https://www.spigotmc.org/threads/compile-spigot-into-a-native-executable-using-graals-native-image.418927/](https://www.spigotmc.org/threads/compile-spigot-into-a-native-executable-using-graals-native-image.418927/)

但是 graalvm native-image 要求所有类都必须在编译时已知, 而 spigot 是一个有插件功能的服务端, 所以显然是无法成功的. 既然如此, 不如直接编译一个原版服务端算了, 如此想着, 就试了一下.

首先从 [Minecraft 官网](https://www.minecraft.net/en-us/download/server)下载一个原版服务端. 原版服务端其实是一个自解压的 fat-jar, 运行的时候会解压出所有依赖的 jar 到工作目录. server.jar 的结构大致是这样的:

```
net/minecraft/bundler
    Main.class    //server.jar 本身的入口类, 用于解压依赖到文件系统
    Main$.*.class    //Main 的一些内部类
META-INF
    libraries    //存放依赖的目录, 里面都是 jar 包
    versions
        1.19.2
            server-1.19.2.jar    //实际运行的服务端
    version.list    //server-1.19.2.jar 的哈希
    MANIFEST.MF    //用于执行 server.jar 本身的清单文件
    main-class    //指引 server-1.19.2.jar 中入口类名称的文件
    libraries.list    //依赖的名称, 版本, 以及文件哈希的列表
    classpath-joined    //用分号分隔的所有依赖的路径
```

众所周知, server.jar 在运行的时候, 会首先把 libraries 解压到工作目录, 然后再用 main-class 里读取到的入口类名称去反射获取到 server-1.19.2.jar 中的实际入口类来执行. 所以调用链到 Main 其实就断了, 无论是之后的通过文件里的入口类名称来反射取得实际入口类还是解压缩依赖到文件系统上再加载这些 jar 包. 为了让 native-image 能成功编译, 就得保持调用链都是正常的, 没有那么多的魔法. 因此要先手动把这一系列操作做完.

将 libraries, versions 文件夹以及 classpath-joined 文件拷贝到同一个文件夹里

```
libraries    //包含了依赖
versions    //包含了 server-1.19.2.jar
classpath-joined    //用分号分隔的所有依赖的路径
```

测试一下能不能正常运行:

```bash
java -cp `cat classpath-joined | tr \; :` net.minecraft.server.Main nogui
```

需要注意两个地方. 一是 java 命令不支持同时使用 -cp 和 -jar, 因此需要手动指定入口类名称, 而 classpath-joined 文件中的最后一项已经包含了 server-1.19.2.jar 所以不需要手动追加到 -cp 里. 二是在 \*nix 系统中, classpath 是使用冒号分隔的, 而不是分号, 因此需要进行字符串替换才能作为 -cp 的值.

Minecraft 所用到的一些库是显然使用了反射的, 比如 log4j 还有 netty. 为了能让 native-image 编译出来的可执行文件中能正常调用这些反射, 需要先在加载了 [native-image-agent](https://www.graalvm.org/22.2/reference-manual/native-image/metadata/AutomaticMetadataCollection/#tracing-agent) 的情况下正常运行一次 Minecraft 服务端来生成反射以及其他魔法的配置文件.

```bash
java -agentlib:native-image-agent=config-output-dir=./config -cp `cat classpath-joined | tr \; :` net.minecraft.server.Main nogui
```

注意, 所用的 java 命令来自于 graalvm 而不是普通的 jdk, 不然是没有 native-image-agent 的.

此命令将在工作目录生成 config 文件夹, 内含所有 native 编译时所需的配置, 这些配置是在运行期间生成的, 任何用到的类都会被写到配置里, 这样就能告诉 native-image 那些单纯通过静态分析不可达的类.

而 Minecraft 服务端在没有玩家时是不运作的, 为了让配置文件充分包含所有需要用到的类, 得先用客户端连接一次服务器, 让所有代码都尽可能运行过.

客户端进入过一次服务器之后关闭服务端, 此时所有配置都会被写入硬盘.

尝试 native 编译:

```bash
native-image -cp `cat classpath-joined | tr \; :` net.minecraft.server.Main -H:ConfigurationFileDirectories=./config -H:+AllowVMInspection --no-fallback --enable-http --enable-https server
```

记得用 -H:ConfigurationFileDirectories 来指定配置文件目录, 另外还需指定 --enable-http 和 --enable-https, Minecraft 访问验证服务器时需要 http 协议, 但这在 native image 默认是关闭的. 为了让 [JFR](https://docs.oracle.com/en/java/java-components/jdk-mission-control/8/user-guide/using-jdk-flight-recorder.html) 正常工作, -H:+AllowVMInspection 也是必须的. 如果使用的 graalvm 是企业版, 还可以使用 --gc=G1 选项.

执行输出的文件:

```bash
./server nogui
```

与正常在 jvm 上运行时一样, 看到 Done 就说明启动成功了.

正常情况下这一份 native 编译出来的服务端是能运行的, 游戏内所有行为也都是正常的. 但是如果出现意外, 比如说虽然能运行, 但是客户端连接无响应且控制台没有报错. 通常是因为有一些错误是 debug 或者 trace 级别的. 通过[命令行参数](https://logging.apache.org/log4j/2.x/manual/configuration.html)来传入 log4j 配置文件来改变 log level

```bash
./server nogui -Dlog4j.configurationFile=log4j2.xml
```

来自 log4j 官网的文件示例:

```markup
<?xml version="1.0" encoding="UTF-8"?>
<Configuration status="WARN">
  <Appenders>
    <Console name="Console" target="SYSTEM_OUT">
      <PatternLayout pattern="%d{HH:mm:ss.SSS} [%t] %-5level %logger{36} - %msg%n"/>
    </Console>
  </Appenders>
  <Loggers>
    <Root level="ALL">
      <AppenderRef ref="Console"/>
    </Root>
  </Loggers>
</Configuration>
```

看到具体错误之后将其解决(例如把缺失的类手动放入 reflect-config.json 配置文件)再重新编译.

与预想的不同, native 编译之后的 Minecraft 并没有显著变快, 包括启动速度. 在我的 AMD 3700X 电脑上(使用企业版 graalvm), native 编译后启动耗时 2.15 秒, 而 JVM 也只需要 2.62 秒(地图已预先生成). 其他该卡的还是照样卡, 比如说跑图, 传送等.

这充分证明了, Minecraft 的卡, 并不是因为 Java 虚拟机, 而是因为代码本身就烂, 尤其是祖宗之法单线程!
