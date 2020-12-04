# 在 Spring Boot 中正确注册 Jackson Module

本文撰写时的 Spring 版本: spring-boot 2.0.5.RELEASE

当我们在进行 `Spring Boot` 开发时, `REST` 接口的默认返回类型是 `json`, 使用的序列化库为 `jackson`.

而 `Spring Boot` 内部使用的 `ObjectMapper` 是在 `MappingJackson2HttpMessageConverter` 里被配置好的. 于是问题就来了, 我们想要使用更多的 `Jackson Module` 怎么办.

默认被配置的 `Jackson Module` 有 `Jdk8Module`, `JavaTimeModule`, `JodaModule`, `KotlinModule`. 这些 Module 是在哪里被注册的呢, 通过搜索大法, 我们发现是在这里 `org.springframework.http.converter.json.Jackson2ObjectMapperBuilder.configure(ObjectMapper objectMapper)`

```java
Assert.notNull(objectMapper, "ObjectMapper must not be null");

if (this.findModulesViaServiceLoader) {
    objectMapper.registerModules(ObjectMapper.findModules(this.moduleClassLoader));
}
else if (this.findWellKnownModules) {
    registerWellKnownModulesIfAvailable(objectMapper);
}

...
```

通过 `IDEA` 的 `find usage` 功能我们可以发现, `findModulesViaServiceLoader` 这个字段永远是 `false`, 根本没有方法会调用他的 `setter`. 更加不要提 `JacksonProperties` 这个设置类了, 所以这段程序永远都会走到 `else if` 分支.

而所谓的 `WellKnownModules` 就是内定的那几个 Module.

我们假设 `jackson-datatype-hibernate5` 是我们想要注册的 Module.

我们查看他的 jar 包会发现 `services` 目录里面有一个文件叫做 `com.fasterxml.jackson.databind.Module`. `ObjectMapper.findModules(this.moduleClassLoader)` 这个方法就是靠这个文件来判断 classpath 中哪些包是他的 Module.

但是因为 `findModulesViaServiceLoader` 永远是 `false`, 所以并非是只要加入依赖就可以实现 Module 的自动注册了.

`KotlinModule` 只要被加入依赖就可以自动注册只是因为他是内定的几个模块之一.

所以简单的加入依赖是没有作用的, 我们必须要用代码来让 `Spring Boot` 知道我们想要使用这个 Module.

于是我们在谷歌上搜索 `spring boot jackson hibernate`, 结果搜到的文章都是教人直接替换 `MappingJackson2HttpMessageConverter`, 可真是一个小机灵鬼.

而一旦替换这个类, 就要把它原本的代码再写一遍, Spring 版本升级之后可能还会挂掉, 所以正确做法一定不是这样.

根据 `Spring Boot` 标准命名法, 自动配置类的类名后缀为 `AutoConfiguration`, 于是我们找到了自动配置 Jackson 的地方 `org.springframework.boot.autoconfigure.jackson.JacksonAutoConfiguration`

然后我们看到在这里 `JacksonAutoConfiguration.Jackson2ObjectMapperBuilderCustomizerConfiguration.StandardJack2ObjectMapperBuilderCustomizer.configreModules(Jackson2ObjectMapperBuilder builder)`

```java
private void configureModules(Jackson2ObjectMapperBuilder builder) {
    Collection<Module> moduleBeans = getBeans(this.applicationContext,
            Module.class);
    builder.modulesToInstall(moduleBeans.toArray(new Module[0]));
}
```

`applicationContext` 中的所有 `Module` 实现类被自动注册了!

所以实际上我们只需要有一个 Bean 就可以实现 Module 的自动注册了.

```kotlin
@Configuration
open class JacksonHibernate5Configuration {
    @Bean
    open fun dataTypeHibernateModule() = Hibernate5Module()
}
```

就这么简单么? 对, 就是这么简单\(这么简单还绕了那么大一圈, 真的菜\)!

