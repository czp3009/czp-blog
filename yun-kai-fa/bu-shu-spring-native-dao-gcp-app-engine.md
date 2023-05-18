# 部署 Spring Native 到 GCP App Engine

本文撰写时的 Spring Boot 版本: 2.7.7, Spring Native 版本: 0.12.2

与其他语言不同的是, App Engine 对 Java 的支持是通过 gradle(或者 maven)插件实现的, 而不是手动执行 gcloud 命令行来提交文件. 但是默认情况下, 插件会找到 **jar 命令**的 artifact 然后提交上去, 所以即使在项目中使用了 spring native, 也不会让部署的文件变成 graalvm native image 的 artifact.

为了迁移到 spring native, 需要配置 gradle, 手动为 appengine 插件指定 artifact

```groovy
appengine {
    stage {
        //假设使用默认的 nativeBuild 输出路径
        artifact = project.buildDir.toPath()
                .resolve('native')
                .resolve('nativeCompile')
                .resolve(project.name)
    }
}
//使 nativeBuild 先于 appengineStage 执行
appengineStage.dependsOn tasks.nativeBuild
```

与此同时, 对应的  app.yaml 文件也需要做出修改. App Engine 并没有单独的对二进制可执行文件的支持, 但是在任何运行时中都可以通过配置 `entrypoint` 来覆盖默认的 docker 的 ENTRYPOINT. 举个例子:

```yaml
service: default
runtime: java17
instance_class: F1

entrypoint: ./application  #二进制文件的文件名, 默认为项目名

handlers:
  - url: /.*
    script: auto
    secure: always
    redirect_http_response_code: 301
```

这样就可以让程序在云上环境中正常运行了.

顺便一提, 在默认情况下 GAE 插件找的也是 jar 命令的输出而不是 bootJar 命令的 artifact, 所以就算不使用 spring native 而是普通的基于 jvm 的 spring, 也要修改插件配置让它用 bootJar 命令输出的 artifact

```groovy
appengine {
    stage {
        artifact = bootJar.archiveFile.get()
    }
}
```
