# 正确打包 Spring Boot 到 war

环境: Spring-Boot 2.0.5.RELEASE \| gradle 4.10.2

众所周知, `Spring Boot` 在开发时之所以能直接启动, 是因为内置了 `tomcat`. 同时这也使得 `Spring Boot` 可以直接输出为可执行的 `jar` 文件.

那么问题来了, 如果我们需要将应用打包为 `war` 文件并部署到外部的 `tomcat` 服务器怎么办.

在 Google 搜索这个问题, 就会看到很多人跟你说, 在 `gradle` 里, 把 `tomcat` 的依赖 exclude 掉就好了. 但是这样的话, 本地调试就没法直接启动了, 既然 `Spring Boot` 的设计是完美的, 所以肯定不是这么弄的.

于是我们找到了 `Spring Boot` 文档 [https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/html/\#packaging-executable-wars](https://docs.spring.io/spring-boot/docs/current/gradle-plugin/reference/html/#packaging-executable-wars)

我们要用 `providedRuntime` 来标记 `tomcat` 的依赖, 这有什么用待会再说.

但是我们马上会发现, `gradle` 找不到符号 `providedRuntime`.

可能是文档有遗漏, 实际上我们必须先启用 `war` 插件\(在此之前应该已经使用了 `spring-boot-gradle-plugin`\)

```groovy
apply plugin: 'war'
```

然后我们的依赖就变为这样

```groovy
dependencies {
    // https://mvnrepository.com/artifact/org.springframework.boot/spring-boot-starter-web
    compile group: 'org.springframework.boot', name: 'spring-boot-starter-web'
    // https://mvnrepository.com/artifact/org.springframework.boot/spring-boot-starter-tomcat
    providedRuntime group: 'org.springframework.boot', name: 'spring-boot-starter-tomcat'
}
```

`providedRuntime` 并不会将依赖从 `classpath` 去除, 所以我们本地开发时依然可以直接启动.

然后我们尝试将应用打包为 `war`

```bash
./gradlew bootWar
```

注意, 执行的 gradle task 为 `bootWar`, 默认的 `war` 会被 `spring-boot-gradle-plugin` 跳过.

我们来看一下打包得到的 `war` 文件的内部结构

```text
META-INF
    MANIFEST.MF
org
    springframework
        boot
            loader
                (Launcher)
WEB-INF
    classes
        (user code)
    lib
        (third party lib)
    lib-provided
        (tomcat)
```

其中的 `MANIFEST.MF` 与普通 `jar` 是一样的, 也就是说, 这个 `war` 可以被当做 `jar` 来执行, 这种 `war` 叫做 `executable war`

```bash
java -jar application-name.war
```

`executable war` 启动时, 实际上的入口类是 `MANIFEST.MF` 中记录的 `Spring Boot Loader` 类.

之后 `lib-provided` 目录也会被其动态加载, 所以可以正常运行.

而这个 `executable war` 同时也确实是一个合法的 `war`, 可以被外部的 `tomcat` 正确加载.

因此, 无论是在开发中直接启动, 还是输出为 `jar` 或 `war` 并当做普通 java 程序来运行, 还是输出为 `war` 并由外部 `tomcat` 加载, 都是正常的.

既然 `Spring Boot` 设计的如此完美, 我们还是要学习一个.
