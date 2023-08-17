# Google Cloud Build 运行 DIND

撰写本文时的 docker 版本: 24.0.5

在使用 CI 时有时候会出现某一个步骤中同时有编译器和 docker 的需求. 举例来说, spring boot gradle plugin 提供的 [bootBuildImage](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image) 任务就是这样的场景. 由于本身是一个 gradle 插件, 因此执行它就必须要有 java, 而它又会去调用 docker daemon, 所以同时又要有 docker.

CI 的每个步骤, 本身是一个 docker. 以 [Google Cloud Build](https://cloud.google.com/build/docs/build-config-file-schema#build\_steps) 为例, 每个步骤中的 `name` 属性表示该步骤所用的 docker image. 如果要在 CI 上运行 bootBuildImage, 就意味着先要准备一个 docker image, 这款 image 中同时有 java 和 docker. docker 在概念上是用来封装单一软件的, 所以 dockerhub 上也找不出这种镜像. 但是自己做一个这样的镜像费心费力还要考虑版本更新的问题.

考虑到 docker 其实分为 [docker CLI](https://docs.docker.com/engine/reference/commandline/cli/) 和 [dockerd](https://docs.docker.com/engine/reference/commandline/dockerd)(docker daemon), 所以我们可以让 spring boot plugin 访问其所在的 docker 外部的 docker daemon(dockerd). 所幸的是 spring boot 确实提供这种功能: [https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-daemon](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-daemon)

只要给 spring boot plugin 提供 `DOCKER_HOST` 环境变量(与正常 docker context 原理一样), 插件就会使用环境变量所指向的 docker daemon 而不是使用默认的本地 daemon. spring boot plugin 并不调用 docker CLI 命令行工具, 而是直接访问 docker daemon API.

## DIND

再考虑到 CI 上的每一个步骤本身是 docker, 换而言之, 只要运行两个 docker, 一个 docker 是 java 镜像, 一个是 docker 是 docker 镜像(简称 [dind](https://www.docker.com/blog/docker-can-now-run-within-docker/)), 然后通过环境变量, 让运行在 java 镜像中的 spring boot 插件去访问另一个 docker 中的 docker daemon, 就能利用(两个)公开镜像完成这一切.

然而事情并没有那么简单, dind 有其特殊性, 使得 dind 必须运行以特权容器的方式运行. CI 执行每个步骤时, 就是简单的 `docker run ${name}`, 比如说:

```bash
docker run --rm docker:dind
```

这条命令被 CI 运行之后, 程序会留下一行报错然后退出:

```
modprobe: can't change directory to '/lib/modules': No such file or directory
mount: permission denied (are you root?)
Could not mount /sys/kernel/security.
AppArmor detection and --privileged mode might break.
mount: permission denied (are you root?)
```

当然 dind 也有非 root 模式(非 host 上的 root, 即非特权)运行的方案:  [https://docs.docker.com/engine/security/rootless/](https://docs.docker.com/engine/security/rootless/)

但是限制太多, 我们没有办法改变 CI 分配的虚拟机的系统设置, 也没有办法改变 CI 执行每一步所用的命令(CI 执行 docker run 命令时不会带有 --privileged 参数), 所以这是不可行的.

因此只能在 CI 启动的 docker 中再套一层. 又考虑到 dind 只是附属品, 是为 java docker 服务的, 所以可以用 docker compose 来一起启动他们. 同时又要考虑到 docker dind 作为后台服务, 是不会自己退出的, 这样会让 CI 一直运行而不结束. 所幸的是, google cloud build 检测 step 结束不是跟 docker 那样检测 pid 0 退出而是不再占用命令行就算此 step 结束了, 因此只要让 dind 后台运行就行了(-d). 而编译器作为另一个 step, 会在编译完成后释放命令行从而结束整个 CI 生命周期.

考虑到以上所有要点后, 首先创建一份 docker-compose.yaml 文件:

```yaml
version: '3'
services:
  dind:
    image: docker:dind
    privileged: true
    command: [ 'dockerd', '-H', 'tcp://0.0.0.0:2375', '--tls=false' ]
    ports:
      - '2375'
networks:
  default:
    name: cloudbuild
    external: true
```

由于我们的 dind 会在 CI 环境中运行, 并不存在安全性问题, 所以最好把 tls 关闭, 否则事情会很麻烦(要映射 dind  tls 证书所在的文件夹到外部然后在所有使用他的地方映射进来). 但是不能通过简单地环境变量来关闭, 此问题详见: [https://github.com/docker/for-linux/issues/1313](https://github.com/docker/for-linux/issues/1313)

能完美关闭 tls 并且较为简单的方案就是如上所述的 issue 中所描述的, 覆盖 dind 默认的 command

而关于 docker network 的描述详见谷歌文档: [https://cloud.google.com/build/docs/build-config-file-schema#network](https://cloud.google.com/build/docs/build-config-file-schema?hl=zh-cn#network)

不能简单地使用 `--network=host`, 而是要使用谷歌文档里所写的 `cloudbuild` 来让所有 docker 都在同一个网络命名空间中.

然后在 cloudbuild.yaml 中启动它并验证启动完成:

```
steps:
  - name: docker
    args: [ 'compose', 'up', '--wait' ]
    id: start-dind
  - name: docker
    args: [ 'version' ]
    env:
      - DOCKER_HOST=tcp://dind:2375
    id: check-dind
```

注意不能简单地为 docker compose 命令使用 -d 选项, 这会让 `docker compose up -d` 在内含的 docker 里的程序启动完毕前就退出(命令行返回). 而刚才说到 google cloud build 只要检测到命令行返回了, 就认为 step 已经结束, 开始执行下一个 step. 这会导致 dockerd 还没启动完毕就开始试图连接它.

`--wait` 参数可以替代 `-id`, 并且会带有[健康检查](https://docs.docker.com/compose/compose-file/05-services/#healthcheck). docker:dind 作为官方 docker, 他是自带健康检查的, 所以不需要在 docker-compose.yaml 中编写.

之后在任何调用 docker 命令的地方都可以通过 `DOCKER_HOST` 环境变量来指示 docker daemon 连接地址. 因为都在同一个网络 `cloudbuild` 中, 所以可以直接使用 domain 来访问.

上述这份 cloudbuild.yaml 在 google cloud build 中运行后, 就会看到成功输出了 docker client 和 docker server 的版本信息. 这说明了 google cloud build 是可以运行后台任务的, 毕竟它检测 step 的完成只是检测了命令行返回. 所以第一个 step 中所启动的 dind 可以一直挂着给后面的 step 使用. 而最后一个 step 的命令行返回时就会结束整个 CI 生命周期, 也不需要特地去关闭 dind(或者 docker compose down).

之后就可以编写更多步骤来进行想要的任务, 比如说构建 spring boot image. 将更多步骤加入到 cloudbuild.yaml:

```yaml
  - name: bellsoft/liberica-openjdk-alpine:17
    entrypoint: ./gradlew
    args: [ 'bootBuildImage' ]
    env:
      - DOCKER_HOST=tcp://dind:2375
    id: bootBuildImage
```

spring boot gradle plugin 执行 `bootBuildImage` 任务时, 除了要给他传递环境变量 [DOCKER\_HOST](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-daemon), 还要注意给他设置 [network](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-daemon) 使其能与 dind 处于同一网络:

```groovy
bootBuildImage {
    network = 'cloudbuild'
}
```

这样一来, 最终执行 gradle 的 docker 就只需要有 java 就行了. spring boot plugin 会跟随环境变量去连接位于另一个 docker 中的 docker daemon 来完成构建. 最后再多加一些 CI step 用来把 dind 中的 docker images 复制出来或者 push 到仓库应该就能大功告成.

## 反转

尽管以上 DIND 的逻辑天衣无缝, 并且有大量的"佐证", 比如说 StackOverflow 上的问答: [https://stackoverflow.com/questions/74305636/how-to-setup-docker-in-docker-dind-on-cloudbuild](https://stackoverflow.com/questions/74305636/how-to-setup-docker-in-docker-dind-on-cloudbuild)

大家都在试图在 Google Cloud Build 上运行 DIND.

然而实际试过之后, `bootBuildImage` 任务执行到最后会有类似这样的错误出现:

```
> Pulling builder image 'docker.io/paketobuildpacks/builder:tiny' ..................................................
> Pulled builder image 'paketobuildpacks/builder@sha256:1a59354925fcb7ba54744b8017630c97c2b035e1a9e19309330557b9c66bfc2c'
> Pulling run image 'docker.io/paketobuildpacks/run:tiny-cnb' ..................................................
> Pulled run image 'paketobuildpacks/run@sha256:adf913cf28031f2090aeaedac65edb36f2987d81a23a8dffab5ea18ca216c94c'
> Executing lifecycle version v0.16.5
> Using build cache volume 'pack-cache-64c1fb6ed79a.build'
> Running creator
> Task :bootBuildImage FAILED
FAILURE: Build failed with an exception. 
* What went wrong:
Execution failed for task ':bootBuildImage'.
> Docker API call to 'dind:2375/v1.24/containers/58ccfb0b58f010fd373a1ad898297ac9af58313baffc95e03539624a315dd91d/start' failed with status code 404 "Not Found"
```

这相当奇怪, 刚产生的 container 怎么突然没了呢. 难道说, 调用到别的 docker daemon 上去了? 是有这种可能, 因为从 spring 文档里的 [bindHostToBuilder](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-daemon) 配置项可以看出, 最后执行起来时 docker 是套了不止一层的. 若是内层的 docker 没有配置正确确实可能调用到别处去了. 但是这种猜测也有问题, 因为错误的内容并非是 connect refuse, 而是 API call 已经成功, 只是 docker daemon 返回 404. 内外层不同的 docker 访问到了不同的 daemon 上并且都能访问到.

换而言之, 整个环境中, 一定有另一个 docker daemon!

那么这个另一个 daemon 到底在哪里呢, 首先大胆假设 Google Cloud Build 会自动给每个 step 映射 `/var/run/docker.sock` 作为 volume. 编写如下 cloudbuild.yaml 来验证:

```yaml
steps:
  - name: docker:latest
    args: [ 'version' ]
```

如果是在自己电脑上模拟 CI 去运行一个 docker 的 docker, 会因为 docker 内部没有 docker daemon 而报错:

```bash
docker run --rm docker:latest version
Client:
 Version:           24.0.5
 API version:       1.43
 Go version:        go1.20.6
 Git commit:        ced0996
 Built:             Fri Jul 21 20:34:32 2023
 OS/Arch:           linux/amd64
 Context:           default
Cannot connect to the Docker daemon at tcp://docker:2375. Is the docker daemon running?
```

但是上面那份 cloudbuild.yaml, 在 CloudBuild 上能成功运行! 答案呼之欲出了, CloudBuild 确实映射了外部的 dockerd 的 domain socket 文件. 类似如下 Linux 命令行:

```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock docker:latest version
```

千言万语, 汇成一句: **艹**

CloudBuild 在生命周期中会创建一台新的 VM, 然后将每个 step 的 name 作为 docker run 的参数: [https://cloud.google.com/build/docs/build-config-file-schema#build\_steps](https://cloud.google.com/build/docs/build-config-file-schema#build\_steps)

所以不止是暴露 docker.sock 那么简单, CloudBuild 向每一个 step 暴露的是宿主(VM)上的 dockerd, 这一 dockerd 会在全部 step 之间共享, 而且 step 本身的 image 也是用此 dockerd pull 下来的.

如果编写一个 step 来调用 `docker images` 命令:

```yaml
steps:
  - name: docker:latest
    args: [ 'images' ]
```

就会看到宿主上全部的 image, 其中包含很多 CloudBuild 预先准备好的 Google 官方构建器: [https://cloud.google.com/build/docs/cloud-builders#supported\_builder\_images\_provided\_by](https://cloud.google.com/build/docs/cloud-builders#supported\_builder\_images\_provided\_by)

这也就是为什么使用官方构建器没有 pull 过程而是直接显示 already have image, 因而速度很快. 比如说如下提示:

```bash
Already have image (with digest): gcr.io/cloud-builders/gcloud
```

## 最终方案

所以类似 Google CloudBuild 这类会自动将宿主的 dockerd 暴露到 CI 步骤内部的情境下, 可以直接使用一个只有 java 的 docker 镜像来完成工作:

```yaml
steps:
  - name: bellsoft/liberica-openjdk-alpine:17
    entrypoint: ./gradlew
    args: [ 'bootBuildImage' ]
  - name: gcr.io/cloud-builders/docker
    args: [ 'images' ]
```

如果要将最终产出的 image 发布到 Google [ArtifactRegistry](https://cloud.google.com/artifact-registry), 不能直接给其设置 publish

```groovy
bootBuildImage {
    publish = true
}
```

一方面是 spring 支持的[验证方式](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/htmlsingle/#build-image.docker-registry)非常有限, 另一方面也是最重要的原因是运行 `bootBuildImage` 任务时, docker client 在内层的 docker 中, 它访问不到外部设置的  docker 配置. 所以没有办法使用凭证帮助程序, 也读不到服务账号.

所以要通过额外步骤将第一步中构建好的 image 取出来并 push 到远端. 谷歌除了映射 dockerd 到每个 step, 谷歌还映射了 client 配置, 所以在 step 中执行 push 是不需要额外鉴权设置的.

举例来说, 首先在 gradle 配置中写好 image name:

```groovy
bootBuildImage {
    imageName = "${gcpProjectRegion}-docker.pkg.dev/${gcpProjectId}/backend/${project.name}"
    tags = ["${imageName.get()}:${project.version}"]
}
```

其中 `gcpProjectRegion` 和 `gcpProjectId` 需另外设置.

spring 默认会用 latest 作为 tag, 可以为其增加额外 tag(非必须的), 比如说如上使用 gradle 中配置的 version 作为 image 的 tag. 这样最终会得到同一个镜像的两个 tag 诸如 "1.0.0" 和 "latest".

然后在之后的步骤中执行类似如下的 shell 脚本, 将 dockerd 中的全部 pkg.dev 的镜像都筛选出来并 push(dockerd 里有谷歌预先准备的其他 image):

```bash
docker images "*-docker.pkg.dev/*/*/*:latest" --format "{{.Repository}}" | while IFS= read -r repository
do
  docker push -a "$repository"
done
```

这回是真的大功告成了
