# 用 Spring Native 拯救微服务

本文撰写时的 Spring Native 版本: 0.11.1

## JVM 微服务的窘境

对于基于 JVM 的微服务应用(尤其是 spring-boot 应用)而言, 长期以来一直存在的一个很大的问题就是启动时间太长. 不只是 JVM 本身启动就相对其他本地语言程序或者脚本语言解释器很慢, 大量的框架也经常通过反射, 代理等在 JVM 上都属于运行速度很慢的功能来完成初始化. 这在具有较高性能的开发机上很容易被忽略, 而大多数微服务平台提供的计算节点的性能都相对来说都非常低, 此问题将变得格外突出. 举个例子, [google cloud app engine ](https://cloud.google.com/appengine/docs/standard#instance\_classes)提供的 front 实例的处理器运行速度默认 600MHZ, 即使通过使用钞能力, 最高也只能达到 2.4GHZ. 这意味着在本地开发机的多核心处理器上启动一个 HelloWorld 级别的 spring boot 应用可能只需要一秒, 但是在微服务平台上这一耗时恐怕将提高到十秒. 对于实例数量可缩减到零的微服务化应用而言, 用户第一次访问将需要等待十秒才能打开页面!

在另一方面, 微服务的动态扩缩和 HotSpot 的 JIT 功能很大程度上是冲突的. 在 server 模式下, 默认的热点方法编译阈值(XX:CompileThreshold)为 10000, client 模式为 1500(JVM 参数的默认值详见 [https://www.oracle.com/java/technologies/javase/vmoptions-jsp.html](https://www.oracle.com/java/technologies/javase/vmoptions-jsp.html)). 而一个微服务容器的一次生命周期内很有可能都调用不到一万次. 也就是说, 在迎来业务代码的性能高峰前, 容器就已经被关闭了. 若调低阈值, 使业务代码能立即被 JIT, 又会显著增加启动耗时, 更得不偿失.

为了避免工作负载启动慢导致用户体验下降, 有一个解决办法是使实例数至少为一. 这样固然可以避免初次访问时过长的等待时间, 但是增加了应用运行成本. 也许对于大企业而言, 让每个服务都至少保持一个实例不是不可接受的, 但是对于小型企业和个人用户(成本敏感型用户)而言, 转向使用看起来更适合用于微服务的 node 或者 go 是更好的选择.

## JAOTC

[jaotc](https://openjdk.java.net/jeps/295) 是随着 Java9 发布的新的命令行工具, 在类编译为字节码后, 可以通过 jaotc 命令来生成 JIT 缓存, 也就是实现了 AOT. 但是它并不是一种让程序脱离 JVM 的解决方案, 它只是提前生成了 JIT 的结果. jaotc 命令最终得到的是一个 so 文件(以 Linux x64 为例)(实际上是静态链接文件), 可以对 class, module, jar 使用. 程序最终运行时仍然和以前一样使用 `java` 命令, 只是多了一个参数 `-XX:AOTLibrary` . 运行起来与以前唯一的区别是 JVM 会从指定的 so 文件里读取 JIT 结果, 而不是在运行时编译热点方法.

jaotc 并没有加快 JVM 本身的启动和大量使用反射与代理的应用框架的初始化过程, 而只是提前编译完所有方法. 更要命的是, jaotc 会带来诸多限制, 例如不能使用 lambda 表达式, 动态调用, 不支持自定义 ClassLoader, 甚至运行时 JVM 参数也必须和 AOT 编译时保持一致等等. 这对大多数框架而言, 等于宣判了 jaotc 的死刑. 并且时至今日, 主流构建工具例如 gradle 都没有对 jaotc 做出实质性的支持, 想要在项目中使用 jatoc 非常麻烦.

## GraalVM

[GraalVM](https://www.graalvm.org) 是 Oracle 新推出的一个 JDK 发行版, 它本身是一个大项目, 包含多个子项目. GraalVM 包含的其中一个项目是 [Truffle Framework](https://www.graalvm.org/graalvm-as-a-platform/language-implementation-framework/), 对于任意语言, 只需要实现到 Truffle 的 AST 解释器, 就可以在 GraalVM 上运行, 更可以和 Java 等其他 JVM 语言互操作, 并且 Truffle 还支持 LLVM. GraalVM 是一种"终极"虚拟机.

![](<../.gitbook/assets/image (71).png>)&#x20;

GraalVM 本身也是 OpenJDK 的超集, 原有的所有已编译好的 Java 字节码也可以在 GraalVM 运行, 并且享受 GraalVM 带来的更好的垃圾回收器. GraalVM 是 Oracle 的次世代 JVM.

但是这些都不重要, 重要的是 GraalVM 的一个名为 [Native Image](https://www.graalvm.org/reference-manual/native-image/) 的子项目, 这是一项可以把 Java 字节码通过 AOT 编译为可单独运行的文件的技术. 经过 Native Image 编译之后, 程序的运行将不再需要 JVM, 编译器会自动将垃圾回收器, 线程管理器等编译到最终输出的可执行文件中(类似 go 语言), 在程序运行时仍可享受与在 JVM 上运行等同的功能.

想要使用 Native Image 就得首先安装 GraalVM, 从 Github 下载最新的 GraalVM(或者从 SDKMAN 安装): [https://github.com/graalvm/graalvm-ce-builds/releases](https://github.com/graalvm/graalvm-ce-builds/releases)

解压到任意目录后执行其中的 gu(GraalVM Updater) 工具以安装 Native Image 组件, 例如:

```bash
./bin/gu install native-image
```

GraalVM 本身是一个 JDK, 运行它不需要首先安装 JDK. 为了方便的使用 GraalVM 包含的各种工具, 建议将其安装目录中的 bin 目录加到 PATH 里, 这样就可以直接使用 gu 命令(使用 SDKMAN 安装的直接可以使用 gu 命令):

```bash
gu install native-image
```

在使用 Native Image 前, 首先要准备好一个可执行的 jar 文件(包含 Main-Class), 可以不是用 GraalVM 编译的, 普通的 OpenJDK 编译的就行. 以一个 HelloWorld 程序为例, 对 jar 文件使用以下命令:

```bash
native-image -jar application.jar
```

在工作目录会产生编译得到的独立的可执行文件 `application` , 来测试一下执行速度(在我的 AMD 3700X 处理器上)

```bash
$ time java -jar application.jar 
HelloWorld!

real	0m0.053s
user	0m0.038s
sys	0m0.011s

$ time ./application 
HelloWorld!

real	0m0.002s
user	0m0.002s
sys	0m0.000s
```

整个程序从启动到执行完毕快了 26.5 倍! JVM 微服务有救了!

但是别高兴的太早, native image 由于脱离了虚拟机, 所以会有一些限制, 比如无法自动分析的反射行为(所用的类或者字符串不是字面量), 以及无法在编译时预知的序列化和动态代理等, 将在编译后无法使用(运行到此处时会抛出异常). 如果所需的反射行为在业务逻辑上是可预知的(例如加载数据库驱动), 只需要将对应的信息写到[配置文件](https://www.graalvm.org/reference-manual/native-image/BuildConfiguration/)里(也可以通过代码方式), 就可以让 native image 在编译时提前对这些类的元信息做摘录从而在运行时正确提供对这些类的反射功能. 然而, 仍然有一些功能是无法实现的, 比如动态加载额外的类, 所以具有插件系统的程序完全不被 native image 支持. 幸运的是, 微服务一般都没有插件系统. 所以对于一个微服务程序而言, 只要把不能自动分析的反射行为写到配置文件里就可以享受 native image 带来的极致体验了!

## Spring Native

[Spring Boot](https://github.com/spring-projects/spring-boot) 作为一个复杂框架, 自然大量使用了非字面量形式的反射, 比如组件扫描. 为此, 要把一个 Spring Boot 应用使用 native image 编译成本地程序, 就需要对所有这些在编译时不可自动分析的反射行为做配置. 而 [Spring Native](https://github.com/spring-projects-experimental/spring-native) 就是为了实现这一点而诞生的.

spring native 例子: [https://github.com/czp3009/spring-native-sample](https://github.com/czp3009/spring-native-sample)

将普通 spring boot 项目改造为 spring native 项目非常简单. 首先, 在 gradle 中加入并启用`org.springframework.experimental.aot` 插件, 如果使用的 [spring native](https://github.com/spring-projects-experimental/spring-native/tree/main/spring-aot-gradle-plugin) 插件版本还未发布到中央仓库, 需手动添加 spring 仓库, 一个典型的 spring native 项目的插件配置可能是这样的(注意, spring boot 版本必须与 spring native 插件相符否则可能不兼容, 详见[文档](https://docs.spring.io/spring-native/docs/current/reference/htmlsingle/#\_validate\_spring\_boot\_version\_2)):

```groovy
buildscript {
    repositories {
        gradlePluginPortal()
        maven { url 'https://repo.spring.io/release' }
    }

    dependencies {
        classpath 'org.springframework.boot:spring-boot-gradle-plugin:2.6.2'
        classpath 'org.springframework.experimental.aot:org.springframework.experimental.aot.gradle.plugin:0.11.1'
    }
}

apply plugin: 'org.springframework.boot'
apply plugin: 'io.spring.dependency-management'
apply plugin: 'org.springframework.experimental.aot'

repositories {
    mavenCentral()
    maven { url 'https://repo.spring.io/release' }
}
```

spring native 插件会自动为项目添加所需的依赖, 同时也会自动加入并配置 [graalvm native build 插件](https://github.com/graalvm/native-build-tools)(旧版插件需要手动添加 graalvm 插件并为 graalvmNative 设置 sourceSet). 如果使用 graalvm 来运行 gradle 本身, 所有的改造工作到此就结束了, 执行插件提供的 `nativeCompile` 任务就可以得到构建产物了.

(需要注意的是, spring native 插件会改变 `build` 任务的前置任务, 在构建时生成 AOT test 有关内容. 所以一旦加入了 spring native 插件, 最好同时加入 'spring-boot-starter-test', 否则原有的 `build` 任务将因找不到引用而出错)

如果不想改变运行 gradle 本身所用的 JVM(java.toolchain), 比如说 OpenJDK, 需要设定环境变量(用 SDKMAN 安装的应该默认就有此环境变量)来指引插件找到 graalvm 安装位置(旧版本插件需要手动为 graalvmNative 配置 javaLauncher):

```bash
export GRAALVM_HOME=/home/czp/graalvm-ce-java11-21.3.0
```

这样就可以用普通 JDK 执行 gradle 与编译项目, 与此同时用 graalvm 来构建 native image 了.

```bash
./gradlew nativeCompile
```

最终输出的构建产物默认在 `build/native/nativeCompile/{project.name}`

除了使用本地的 graalvm 来执行构建, 还可以使用 [buildpack](https://docs.spring.io/spring-native/docs/current/reference/htmlsingle/#\_enable\_native\_image\_support).

graalvm native image 构建所需的资源非常多, 一个 HelloWorld 级别的 spring boot 项目大约需要 7GiB 内存, 以及大量的 cpu 时间(在我的 i7-8700 上耗时一分钟), 如果想要在云 CI 上执行构建, 请确保云上环境有足够高的配置.

## 性能对比

native image 带来的性能提升是巨大的, 首先是启动速度(在同一台机器上):

```
$ java -jar spring-native-sample-1.0.0.jar 

  .   ____          _            __ _ _
 /\\ / ___'_ __ _ _(_)_ __  __ _ \ \ \ \
( ( )\___ | '_ | '_| | '_ \/ _` | \ \ \ \
 \\/  ___)| |_)| | | | | || (_| |  ) ) ) )
  '  |____| .__|_| |_|_| |_\__, | / / / /
 =========|_|==============|___/=/_/_/_/
 :: Spring Boot ::                (v2.6.1)

2021-12-19 16:46:33.141  INFO 17539 --- [           main] org.example.ApplicationKt                : Starting ApplicationKt using Java 11.0.13 on czp-ubuntu with PID 17539 (/home/czp/spring-native-sample-1.0.0.jar started by czp in /home/czp)
2021-12-19 16:46:33.144  INFO 17539 --- [           main] org.example.ApplicationKt                : No active profile set, falling back to default profiles: default
2021-12-19 16:46:34.168  INFO 17539 --- [           main] o.s.b.web.embedded.netty.NettyWebServer  : Netty started on port 8080
2021-12-19 16:46:34.176  INFO 17539 --- [           main] org.example.ApplicationKt                : Started ApplicationKt in 1.33 seconds (JVM running for 1.613)


$ ./spring-native-sample 
16:47:58.900 [main] INFO org.springframework.boot.SpringApplication - AOT mode enabled
2021-12-19 16:47:58.906  INFO 17714 --- [           main] o.s.nativex.NativeListener               : This application is bootstrapped with code generated with Spring AOT

  .   ____          _            __ _ _
 /\\ / ___'_ __ _ _(_)_ __  __ _ \ \ \ \
( ( )\___ | '_ | '_| | '_ \/ _` | \ \ \ \
 \\/  ___)| |_)| | | | | || (_| |  ) ) ) )
  '  |____| .__|_| |_|_| |_\__, | / / / /
 =========|_|==============|___/=/_/_/_/
 :: Spring Boot ::                (v2.6.1)

2021-12-19 16:47:58.908  INFO 17714 --- [           main] o.s.boot.SpringApplication               : Starting application using Java 11.0.13 on czp-ubuntu with PID 17714 (started by czp in /home/czp)
2021-12-19 16:47:58.908  INFO 17714 --- [           main] o.s.boot.SpringApplication               : No active profile set, falling back to default profiles: default
2021-12-19 16:47:58.922  INFO 17714 --- [           main] o.s.b.web.embedded.netty.NettyWebServer  : Netty started on port 8080
2021-12-19 16:47:58.923  INFO 17714 --- [           main] o.s.boot.SpringApplication               : Started application in 0.022 seconds (JVM running for 0.023)
```

从命令开始执行到 spring boot 初始化完毕, native image 编译后的**启动速度提升了七十倍**! 从 1.613 秒降低至 0.023 秒! 这样的启动速度甚至快过几乎所有脚本语言, 完全满足微服务的需要.

接下来是前几次访问耗时(不包含启动时间):

```
$ for i in {1..5}; do curl -w '%{time_total}\n' -o /dev/null -s localhost:8080/ping; done
0.157708
0.009744
0.007514
0.009595
0.007608


$ for i in {1..5}; do curl -w '%{time_total}\n' -o /dev/null -s localhost:8080/ping; done
0.003939
0.000716
0.000850
0.000726
0.000763
```

在 native image 编译后, 首次访问速度提升了四十倍, 其后的访问提升了十倍!

模拟一千个用户一共访问十万次:

```
$ ab -n 100000 -c 1000 http://localhost:8080/ping
This is ApacheBench, Version 2.3 <$Revision: 1843412 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 10000 requests
Completed 20000 requests
Completed 30000 requests
Completed 40000 requests
Completed 50000 requests
Completed 60000 requests
Completed 70000 requests
Completed 80000 requests
Completed 90000 requests
Completed 100000 requests
Finished 100000 requests


Server Software:        
Server Hostname:        localhost
Server Port:            8080

Document Path:          /ping
Document Length:        10 bytes

Concurrency Level:      1000
Time taken for tests:   4.103 seconds
Complete requests:      100000
Failed requests:        0
Total transferred:      8900000 bytes
HTML transferred:       1000000 bytes
Requests per second:    24370.69 [#/sec] (mean)
Time per request:       41.033 [ms] (mean)
Time per request:       0.041 [ms] (mean, across all concurrent requests)
Transfer rate:          2118.16 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0   19   4.3     19      37
Processing:     3   21   4.6     21      43
Waiting:        1   13   4.4     13      40
Total:         23   41   2.6     40      75

Percentage of the requests served within a certain time (ms)
  50%     40
  66%     41
  75%     41
  80%     42
  90%     44
  95%     46
  98%     48
  99%     49
 100%     75 (longest request)


$ ab -n 100000 -c 1000 http://localhost:8080/ping
This is ApacheBench, Version 2.3 <$Revision: 1843412 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 10000 requests
Completed 20000 requests
Completed 30000 requests
Completed 40000 requests
Completed 50000 requests
Completed 60000 requests
Completed 70000 requests
Completed 80000 requests
Completed 90000 requests
Completed 100000 requests
Finished 100000 requests


Server Software:        
Server Hostname:        localhost
Server Port:            8080

Document Path:          /ping
Document Length:        10 bytes

Concurrency Level:      1000
Time taken for tests:   3.996 seconds
Complete requests:      100000
Failed requests:        0
Total transferred:      8900000 bytes
HTML transferred:       1000000 bytes
Requests per second:    25024.10 [#/sec] (mean)
Time per request:       39.961 [ms] (mean)
Time per request:       0.040 [ms] (mean, across all concurrent requests)
Transfer rate:          2174.95 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0   19   2.4     19      34
Processing:     7   21   3.5     20      44
Waiting:        1   15   3.5     15      36
Total:         22   40   3.0     39      65

Percentage of the requests served within a certain time (ms)
  50%     39
  66%     39
  75%     40
  80%     40
  90%     44
  95%     47
  98%     49
  99%     51
 100%     65 (longest request)
```

在 JVM 上, 在十万次请求中(测试前已预热一百万次请求), 程序每秒可响应 24370.69 次请求, 在 native image 编译后每秒响应 25024.10 次, 负载量差别不大. 也就是说, JVM 在充分预热之后, 性能巅峰才与 native image 的性能等同.

在内存方面, 在初次启动并访问后, JVM 消耗了 151 MiB 内存(不包含已申请但未使用的内存), native image 编译后的程序仅占用 27 MiB 内存.

关于磁盘占用量, 原 jar 文件大小 25.1 MB, native image 编译后 71.7MB, 如果项目包含的代码(包括依赖)更多, 相比较原 jar, 文件容量的差距的倍率会更大.

在真实生产环境(google cloud app engine)中, 可以明显感受到区别. 以 F1 实例为例, 使用了 native image 后, 在第一次请求中, 从用户请求抵达 app engine 开始到响应返回, 共耗时 1.635 秒, 其中程序启动与初始化耗时 0.815 秒. 而在使用 JVM 的情况下, 用户要盯着一片雪白的浏览器加载界面看八秒! 更致命的是如果依赖比较多, 导致所需的内存更大, F1 实例的 256 MB 内存会不够用, 还得加钱升级实例, 钱和用户体验双双丢失.

## 实用性

目前 spring native 已经支持几乎全部 spring 功能(不支持的功能详见 [https://docs.spring.io/spring-native/docs/current/reference/htmlsingle/#\_starters\_requiring\_no\_special\_build\_configuration](https://docs.spring.io/spring-native/docs/current/reference/htmlsingle/#\_starters\_requiring\_no\_special\_build\_configuration)), 除了 spring 全家桶, 其他库也纷纷推出自己的对 native image 的支持, 比如 [google cloud native image support](https://github.com/GoogleCloudPlatform/native-image-support-java), 扫除了将 native image 应用到生产环境的障碍.

将原有的微服务逐步迁移到 native image 是一个很好的选择, 快来试试吧!
