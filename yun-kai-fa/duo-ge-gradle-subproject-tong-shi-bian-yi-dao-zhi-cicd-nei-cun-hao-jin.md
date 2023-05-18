# 多个 Gradle SubProject 同时编译导致 CI/CD 内存耗尽

撰写此文时的 Gradle 版本: 7.6

Gradle 有一个很好的功能, 它叫做 parallel, 只要在 gradle.properties 文件中开启此功能就能让项目中的多个 sub project 同时编译, 充分利用了所有核心, 大大加快了整体编译速度

```properties
org.gradle.parallel=true
```

但是对 CI/CD 来说这可能产生问题. 大多数情况下 CI 环境可用的资源不会太多, 比如 4GiB 内存差不多是各种免费 CI 的极限了. 而一旦 parallel 起来, 很容易就把 CI 内存用完导致构建失败.

虽然 CI 环境的内存可以通过加钱来调大, 但是公有云上的 CI 不能无限增加配置. 在某些情况下依然会遇上内存不够的问题, 例如说使用 graalvm native image 的时候, 一个 spring native 的 sub project 编译可能就要 8GiB 内存, 四个 sub project 一起编译就要耗费超过 32GiB 内存, 而绝大多数公有云提供的最高规格的 CI 配置也就 32GiB. 如果 sub project 更多, 在公有云上就没有办法并行构建了.

这就产生了矛盾, 又想要并行构建充分利用 CPU, 又不想全部 sub project 都并行而耗尽内存. 想并行, 但只想并行一点点.

其实除了内存耗尽的问题之外, 并行构建还会遇到另一个问题是不能很好地支持会访问外部资源的任务. 例如说一个任务会下载一个互联网文件到固定路径上, 但是这个任务在每个 sub project 中都有, 所以实际执行的时候就是多个任务实例同时下载这个文件, 同时写入到硬盘, 最终结果就是出错.

这里给出一个典型案例: 在一个大 project 中包含了多个 sub project, 每个 sub project 对应一个 GoogleCloud AppEngine service, 且其中一些 service 使用了 graalvm native image. 而 GoogleCloud Build 最大只提供 32GiB 内存.

这个案例的一种解决方案是手动分批执行 gradle 任务. 由于 gradle 在执行任务时会首先检查是否已经有缓存, 如果有缓存就会跳过此任务, 因此渐进式执行不会导致前面的任务被执行多次. 以下述脚本为例

```bash
# download gcloud commandline tool before deploy to avoid concurrency downloading
# build all services at first to fully utilize the multi-core cpu
./gradlew default-service:downloadCloudSdk bootJar
# build graalvm native image services one by one(small projects are merged) to reduce memory footage
./gradlew a-service:nativeCompile b:nativeCompile
./gradlew c:nativeCompile d:nativeCompile
# deploy all service
./gradlew appengineDeploy
```

在 CI 中并不直接执行 `appengineDeploy` 而是先把该执行的任务都执行好了(把 artifact 都构建出来)再执行 `appengineDeploy` 把服务一口气部署上去(`appengineDeploy` 在每个 sub project 里都有, 所以多个 `appengineDeploy` 任务实例会同时执行).

`downloadCloudSdk` 这个任务会下载 gcloud 命令行工具到系统上, 这一步是不能并行的, 所以必须让某一个 sub project 的 `downloadCloudSdk` 任务先执行好(此时只有一个任务在执行), 才能继续执行其他任务. 在下载的过程中, 只有网络资源在被消耗, 所以顺便把所有服务的 jar 包构建出来, 把 CPU 也利用起来.

会导致内存耗尽的就是接下去的构建 native image 的过程. 部分 sub project 构建时可能会耗用超过 10GiB 内存, 所以在脚本中使其两个两个编译, 确保其总内存不会超过 32GiB.

所有需要耗很多内存的任务, 都在上面渐进式一波一波执行好了之后, 最后再启动 `appengineDeploy` 就一定能成功部署了.
