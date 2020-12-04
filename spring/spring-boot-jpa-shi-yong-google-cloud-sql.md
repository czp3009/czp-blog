# Spring Boot Jpa 使用 Google Cloud SQL

本文撰写时的 Spring 版本: Spring-Boot-Data-Jpa 2.0.5.RELEASE

编写使用 `Google Cloud SQL` 的应用时, 我们会发现一个问题. 当我们连接 Cloud SQL 时, 我们必须先知道 `Cloud SQL` 的 `IP`, 同时还要到 `Google Cloud Console` 给自己的应用所部署的服务器加数据库白名单\(如果是部署在同一项目下的 `APP Engine` 或者 `Compute Engine` 默认就有白名单\).

一个应用还可以操作一下, 如果写的是微服务, 有几十个应用, 那简直是浑身爆炸.

于是我们想到, 有没有这么一种库, 可以帮我们自动寻找所需的 `Cloud SQL` 的真实地址并且自动完成身份验证呢.

秉承 '不写任何代码' 的光荣传统, 我们找到了这个库 [https://github.com/spring-cloud/spring-cloud-gcp/tree/master/spring-cloud-gcp-starters/spring-cloud-gcp-starter-sql-mysql](https://github.com/spring-cloud/spring-cloud-gcp/tree/master/spring-cloud-gcp-starters/spring-cloud-gcp-starter-sql-mysql)

## 添加依赖

这个库只实现 `mysql-socket-factory`\([https://github.com/GoogleCloudPlatform/cloud-sql-jdbc-socket-factory](https://github.com/GoogleCloudPlatform/cloud-sql-jdbc-socket-factory)\) 的自动配置, JPA 的依赖还是要加的. 所以依赖至少有如下两个

```groovy
dependencies {
    // https://mvnrepository.com/artifact/org.springframework.boot/spring-boot-starter-data-jpa
    compile group: 'org.springframework.boot', name: 'spring-boot-starter-data-jpa'
    // https://mvnrepository.com/artifact/org.springframework.cloud/spring-cloud-gcp-starter-sql-mysql
    compile group: 'org.springframework.cloud', name: 'spring-cloud-gcp-starter-sql-mysql', version: '1.0.0.RELEASE'
}
```

注意, `spring-cloud-gcp-starter-sql-mysql` 并非是 Spring 官方自己写的, 所以 Spring 插件并不能管理他的版本号. 这里的版本号是必写的\(或者用 dependency management 全局指定\).

自动包含的 `MySQL 驱动` 是 `5.1.x`, 如果要使用更高版本必须注意 `MySQL 驱动` 版本与 `mysql-socket-factory` 版本相匹配. 版本兼容对照表详见 [https://github.com/GoogleCloudPlatform/cloud-sql-jdbc-socket-factory\#mysql](https://github.com/GoogleCloudPlatform/cloud-sql-jdbc-socket-factory#mysql)

## 配置文件

配置文件至少包含以下项目

```text
spring.cloud.gcp.sql.instance-connection-name=project-name:asia-northeast1:mysql-name
spring.cloud.gcp.sql.database-name=database_name
spring.jpa.database-platform=org.hibernate.dialect.MySQL55Dialect
spring.jpa.hibernate.ddl-auto=update
spring.datasource.username=root
spring.datasource.password=123456
spring.cloud.gcp.credentials.location=classpath:/service-account-key/key.json
```

那么, 我们从哪里获取这些配置所需的内容呢.

首先打开自己的 `Google Cloud Platform` 项目管理页面.

左侧侧拉抽屉选择 `SQL`, 再选择一个已创建的实例.

在实例的管理页面, 看到有个卡片叫做 `连接到此实例`, 里面的 `实例连接名称` 就是我们的 `spring.cloud.gcp.sql.instance-connection-name`

实例管理页面上方的 `数据库` 标签页里面可以创建数据库, 创建好了之后把数据库名填到 `spring.cloud.gcp.sql.database-name`

如果要使用自动建表功能, `database-dialect` 必须手动设置. 因为 `Cloud SQL` 不支持 `MyISAM`, Hibernate 不一定会使用 `InnoDB` 引擎来建表从而导致建表操作抛出异常.

很久之前 `MySQL` 就默认使用 `InnoDB` 了, 所以 `Dialect` 设置为 `MySQL5` 即可.

```text
spring.jpa.database-platform=org.hibernate.dialect.MySQL55Dialect
```

`用户` 标签页用来创建数据库账号, 创建好了填写到 `spring.datasource.username/password`

为了让 `Cloud SQL` 识别我们的身份, 我们需要一个凭证, 从而免去手动添加白名单的麻烦.

通常 APP 并不以个人用户身份登陆, 而是以一个专门的 `Service Account` 来作为身份凭证. 现在我们去创建一个 `Service Account`

点击项目的管理页面左边侧拉抽屉里的 `IAM 和管理` &gt; `服务账号` 进入服务账号管理页面.

然后创建一个服务账号, 创建好了之后查看他的详情, 点击 `创建密钥`, 生成并下载一个 `json` 文件.

这份 `json` 就是我们在程序里要用的.

我们把这份文件放到一个位置, 哪里都可以, 只要用 URI 可以表示即可. 上面的示例中, 我们将此文件放到了项目的 `/src/main/resources/service-account-key/key.json`

然后我们配置他的位置

```text
spring.cloud.gcp.credentials.location=classpath:/service-account-key/key.json
```

如果我们不能在配置文件中指定它的位置, 我们还可以使用环境变量来指定它的路径, 详见 [https://cloud.google.com/docs/authentication/getting-started](https://cloud.google.com/docs/authentication/getting-started)

如果此应用最终部署到 `ComputeEngine` 或者 `AppEngine` 不需要指定证书文件路径, spring-boot-gcp 会自动从环境中获得. 但是为了本地测试, 证书文件终究还是要创建的.

很好, 现在我们完成了配置.

## 问题

启动程序的时候我们可能会看到程序提示没有 `sqladmin` 权限, 并给出了一个链接, 但是这个链接是个 404 地址.

由于需要访问相关 API 来获取 `Cloud SQL` 实例的真实地址并传给 JPA, 所以我们需要让项目开放一个 API, 用来给程序查询 `Cloud SQL` 实例信息.

而这个页面是一个 404 页面可能是因为 SDK 版本过旧, Web 控制台已经更新而 SDK 中的异常提示文本没有更新.

那么如何找到这个 API 呢.

我们在项目管理页上方的搜索框输入 `cloud sql admin`, 就可以找到它了.

启用这个 API 并重启程序, 就可以正确连接数据库了.

