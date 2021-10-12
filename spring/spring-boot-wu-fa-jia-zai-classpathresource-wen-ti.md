# Spring Boot 无法加载 ClasspathResource 问题

本文撰写时的 Spring 版本: Spring Boot 2.0.5.RELEASE

一般来说, 程序运行所必须的资源文件我们会一起打包到 `jar`. 那么接下去我们就要读取这个资源文件.

假定我们的资源文件在源码目录中为 `src/main/resources/myFile.txt`

在 `Spring Boot` 中读取存放在 `classpath` 的资源文件通常是这么做的

```kotlin
val myFile = ClasspathResource("/myFile.txt")
```

(不同于 `Class.getResourceAsStream(name:String)`, `ClasspathResource` 的参数可以去除第一个 `/`)

然后我们读取这个文件的内容通常是先获取他的流

```kotlin
myFile.inputStream
```

这好像没有什么问题, 文件也正常的被读取了.

一些第三方库可能要求传入一个 `File` 类型.

```kotlin
myFile.file
```

看起来也没有问题, 并且在 `IDEA` 里运行的时候确实没有问题. 直到 CI 测试的时候, 就会有这么一个异常

```
java.io.FileNotFoundException: class path resource [myFile.txt] cannot be resolved to absolute file path because it does not reside in the file system: jar:file:/app.jar!/BOOT-INF/classes!/myFile.txt
```

在集成环境和生产环境上, 我们的程序是一个 `jar` 包而不是 `exploded` 方式, 也就是说, 此时的 `Resource.getFile()` 将有不一样的行为.

我们在 `IDEA` 调试的时候, 资源文件是存在于真实文件系统里的一个文件. 而在 `jar` 包中, 它不是一个真实文件系统的文件.

为了能用统一的文件系统路径去表示 `jar` 内的文件, Java 开创了 `!` 这个符号.

`!` 表示这个文件是一个压缩包(zip)(jar 本身就是一个 zip), 之后的路径则为压缩包内的路径(压缩包内的路径不分运行平台, 统一为 Unix 路径).

正常情况下的 `getFile()` 操作, 会得到一个 `jar` 包路径后面加上一个 `!` 号然后再拼接上包内路径的一个路径.

`Spring Boot` 为了避免资源文件冲突(Java 的打包规范忽略了资源文件的问题, 两个库的代码文件是可以合并的, 因为包名不同. 但是资源文件都从 `jar` 的根目录开始编排, 如果重名将互相覆盖而导致打包后资源文件的丢失)而采用 `fat-jar` 的方式来打包程序.

`fat-jar` 就是一种 `nested jar`, 所有的依赖库不会合并到用户代码上, 而是以 `jar` 包的形式存放在 `jar` 包内.

一个典型的 `Spring Boot` 程序打包后差不多是这样的

```
META-INF
    MANIFEST.MF
org
    springframework
        boot
            loader
                (Launcher)
BOOT-INF
    classes
        (user code)
    lib
        dependence.jar
```

`jar` 的入口类其实是 `Spring Boot Launcher`, 他会为每一个依赖创建一个 `ClassLoader`, 这样就可以让每个依赖自己读取自己的资源文件而互不冲突.

而用户自己的类是从 `/BOOT-INF/classes` 开始的, 用户自己的资源文件的根目录也在这里, 所以为了让用户能够正确读到自己的资源文件. 加载用户代码的那个 `ClassLoader` 的 `classpath` 从这里开始.

`fat-jar` 并不是 Java 官方标准, 所以 Java 认为所有 `classpath` 都是从 `jar` 的根目录开始的.

于是我们得到的文件路径, 将是 `{用户代码根目录}!/{资源文件路径}`

而用户代码根目录本身就是在 `jar` 内的, 最终我们会得到这么一个路径

```
jar:file:/app.jar!/BOOT-INF/classes!/myFile.txt
```

(注意, 有两个 `!` 号)

没错, `classes` 文件夹被认为是一个压缩包了.

所以我们将找不到这个文件.

如果读取资源文件的操作只在自己的代码发生, 那么只要不使用 `Resource.getFile()` 而直接获取流就可以避免这个问题. 但是很多情况下, 并非自己要读文件, 而是第三方库要读文件.

例如第三方库可能会要求在配置文件中配置 `key` 文件的路径, 而这个路径支持网络读取, 所以必须是 URI. 然后第三方库的代码中就会使用 `Resource.getFile()` 来把这个地址转成 `File` 类型再去读他.

这么一读, 就抛出异常了.

那么, 怎么办呢.

我们找到了这么一个库 [https://github.com/ulisesbocchio/spring-boot-jar-resources](https://github.com/ulisesbocchio/spring-boot-jar-resources)

他的功能是通过自定义的 `ResourceLoader`, 当 `Spring Boot` 需要读取文件时, 首先判断这个文件是不是存在于 `classpath` 中, 如果是, 则解压这个文件到临时目录(真实文件系统上), 然后返回文件系统路径.

使用非常简单, 首先加入依赖

```groovy
// https://mvnrepository.com/artifact/com.github.ulisesbocchio/spring-boot-jar-resources
compile group: 'com.github.ulisesbocchio', name: 'spring-boot-jar-resources', version: '1.3'
```

然后把程序入口改成这样就行了

```kotlin
@SpringBootApplication
open class Application

fun main() {
    runApplication<Application> {
        resourceLoader = JarResourceLoader()
    }
}
```

我们再使用 `ApplicationContext.getResource()` 时, 返回的就不是 `ClasspathResource` 了, 而是 `JarResource`, 路径在一个临时目录下(Linux 下默认为 `/tmp/**`)

这样, 我们就可以让第三方库正常工作了.
