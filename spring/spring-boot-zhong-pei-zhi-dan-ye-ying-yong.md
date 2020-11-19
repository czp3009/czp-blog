# Spring Boot 中配置单页应用

环境: Spring Boot 2.1.0.RELEASE

有的时候我们不得不把一个单页应用\(例如 `react-router`\)与 `Spring-Boot` 后端一起打包. 但是这样就会有一个问题, 一旦用户刷新页面, 就会看到一个 404 画面, 因为服务端并没有把请求转向 `index.html`.

所以我们通过一些配置, 让 Spring 在找不到对应的资源文件的情况下, 将请求统统转向到 `index.html`, 这样用户就可以前端路由了.

我们很容易想到让 Spring 找不到资源文件时抛出一个异常然后我们配置一个 `ControllerAdvice`, 于是我们搜到了这么一条配置

```text
spring.mvc.throw-exception-if-no-handler-found=true
```

但是我们很快就发现, 实际上 `NoHandlerException` 永远不会被抛出.

我们来看一下 Spring 是如何路由请求的, 我们打开 `DispatcherServlet` 的源码

```java
@Nullable
protected HandlerExecutionChain getHandler(HttpServletRequest request) throws Exception {
    if (this.handlerMappings != null) {
        for (HandlerMapping mapping : this.handlerMappings) {
            HandlerExecutionChain handler = mapping.getHandler(request);
            if (handler != null) {
                return handler;
            }
        }
    }
    return null;
}
```

默认情况下, `HandlerMapping` 有七个, 分别为

```text
SimpleUrlHandlerMapping
WebMvcEndpointHandlerMapping
ControllerEndpointHandlerMapping
RequestMappingHandlerMapping
BeanNameUrlHandlerMapping
WelcomePageHandlerMapping
SimpleUrlHandlerMapping
```

第一个 `SimpleUrlHandlerMapping` 用于处理 `/favicon.ico` 这就是为什么目录里没有 icon, 也会有默认的站点图标.

而最下面的第二个 `SimpleUrlHandlerMapping` 用于处理资源文件, 而资源文件都是 `lazy loading` 的, 并不是像 `HomePage(index.html)(硬编码)` 或者 `favicon.ico(硬编码)` 那样是一开始就加载好的. 所以第二个 `SimpleUrlHandlerMapping` 的 `getHandler` 方法永远都会有一个指向所有资源文件路径的 `HandlerExecutionChain` 被返回回来.

因此即使目标资源文件不存在, 我们的 `Handler` 也不是 `null`, 永远也不会有 `NoHandlerException` 被抛出.

而在一些解决方案中, 是把默认的 `ResourceHandler` 关掉,

```text
spring.resources.add-mappings=false
```

这样最后一个 `HandlerMapping` 就不会有了, 异常就会被抛出了.

但是这么做的话, 我们就要面对另一个更大的问题, 没有了默认的 `ResourceHandler`, 我们的资源文件就不能被正常读取, 我们需要自己编写额外写代码来让他们能被读取, 这完全是重复编码.

所以正确的解决方案只能是让最后一个 `SimpleUrlHandlerMapping` 在找不到目标资源的情况下, 读取 `index.html` 交给访问者.

我们通过一个自定义的 `ResourceHandler` 来实现它

```kotlin
@Configuration
open class SinglePageApplicationWebMvcConfiguration(
        private val resourceProperties: ResourceProperties
) : WebMvcConfigurer {
    override fun addResourceHandlers(registry: ResourceHandlerRegistry) {
        registry.addResourceHandler("/**")
                .addResourceLocations(*resourceProperties.staticLocations)
                .resourceChain(true)
                .addResolver(object : PathResourceResolver() {
                    override fun getResource(resourcePath: String, location: Resource): Resource? =
                            super.getResource(resourcePath, location) ?: super.getResource("index.html", location)
                })
    }
}
```

注意, `spring.resources.static-locations` 并非是硬编码的, 而是在配置文件中可以修改的, 所以我们要从配置文件中得到它.

该 `ResourceHandler` 将监听 `/**` 地址\(注意, `RequestMappingHandlerMapping` 一定是先于最后一个 `SimpleUrlHandlerMapping` 被执行的, 所以访问 RestFul API 的请求不会进入 `ResourceHandler`\), 当目标资源不存在时, 将返回 `index.html`, 如果 `index.html` 也不存在, 将产生 404.

`addResourceLocations` 添加的资源位置, 会让 `Resolver` 在每个资源位置都被轮询一次, 所以不会因为用户额外添加了 `static-location` 而导致错误.

`resourceChain(true)` 设置了资源位置缓存, 例如本次访问了 `index.js` 并在 `/public` 位置被找到, 那么下次将直接从该位置读取该资源文件, `Resolver` 不会被执行.

这样, 我们就在不重写 Spring 默认逻辑的情况下将所有未识别的访问引导到 `index.html`, 从而完成了单页应用的配置.

接下去, 用户将在前端被路由, 从而看到正确的页面.

