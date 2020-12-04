# GCP Pub/Sub push 至 IAP 保护的 AppEngine

众所周知 [IAP](https://cloud.google.com/iap/docs)\(Identity Aware Proxy\)对 AppEngine 开启后, 所有 GAE 的 service 都需要先通过 OAuth2 鉴权才能访问. 普通用户在浏览器打开 IAP 保护的 GAE 地址就会被跳转到登录页面去登录谷歌账号. 那问题来了, Pub/Sub 的消息如果要推送到一个 IAP 保护的地址怎么办.

创建 subscription 的时候如果选择了 Push 方式, 下面就会有一个 Enable Authentication 的勾选框. 填好 Endpoint\(必须得是谷歌可以验证其所属的地址, 比如 GAE\) 后再选择一个用来产生 JWT 签名的服务账号. 命令行操作可以看 [这里](https://cloud.google.com/pubsub/docs/push?&_ga=2.110393165.-939952904.1598953677&_gac=1.256953081.1606183934.CjwKCAiA2O39BRBjEiwApB2IkmhMo1jn8TcOqrVfoniXrotePn5M0mR_ZVpVeQMYEYZMpr7I2FRUkRoCz7gQAvD_BwE#setting_up_for_push_authentication) , 特别需要注意的是, 如果一个 subscription 是在命令行创建的而不是在网页控制台创建的, 需要额外给予 `Service Account Token Creator` 权限\(此权限甚至不包含在 Project Admin\)给 Pub/Sub 的 service account\(这跟用来产生 JWT 签名的服务账号不是同一个\) `service-$PROJECT_NUMBER@gcp-sa-pubsub.iam.gserviceaccount.com` , 其中 'PROJECT\_NUMBER' 是表示项目编号的变量\(在项目的 dashboard 查看\).

![](../.gitbook/assets/image%20%2864%29.png)

之后就像授权普通用户访问 IAP 那样给 JWT 签名用的服务账号添加 `IAP-secured Web App User` 权限. 然后来试一试.

随便发个消息, 以为已经弄好了, 结果消息一直没有 ACK.

更气人的是看不到 IAP 的访问日志, 日志里只有 IAP 设置的修改.

这一部分日志属于 API 内部日志, 不由用户代码产生, 默认不记录这些日志, 要先去 `Audit Logs` \(在网页控制台搜索这一页面\)打开 IAP 日志.

![](../.gitbook/assets/image%20%2862%29.png)

在 `Logging` 中按以下 Query 来查询日志

```text
resource.type="gae_app"
```

可以在左边的 Log Fields 看到一个 `module_id` 是 null 的东西

![](../.gitbook/assets/image%20%2863%29.png)

这个没有名字的模块就是 IAP 的访问日志, 由于它没有名字不能单独筛选, 只能混在所有 module 的日志里一起看.

然后就会看到一个双感叹号的条目, 赫然写着 'Permission Denied'. 这说明 Pub/Sub 发出的请求没有通过 IAP 验证.

服务账号访问 IAP 保护的资源实际上就是先签出一个 JWT, 然后用这个 JWT 去访问资源. 签名过程中有几个关键性参数 `client_id` `client_secret` `audience` . 既然创建 subscription 时没有要求选前两个参数, 说明是通过 service account 逆推出来的. 那么无法验证一定是因为 `audience` 是必填的. 刚才创建 subscription 时 audience 后面有个括号 optional, 显然为了正常验证这是必填的.

在 StackOverflow 上搜索这个问题只有一个结果, 评论区有人说还需要把 audience 设置为 OAuth2 Client ID. 先到 [https://console.cloud.google.com/apis/credentials](https://console.cloud.google.com/apis/credentials) 找到对应的服务账号的 Client ID 把他填到 subscription 的 audience, 结果依然 Permission Denied.

我们回到 IAP 的文档去看 Programmatic Authentication 一节 [https://cloud.google.com/iap/docs/authentication-howto\#accessing\_the\_application](https://cloud.google.com/iap/docs/authentication-howto#accessing_the_application)

文档提供的获得 JWT 的 curl 命令是这样的

```text
curl --verbose \
      --data client_id=DESKTOP_CLIENT_ID \
      --data client_secret=DESKTOP_CLIENT_SECRET \
      --data refresh_token=REFRESH_TOKEN \
      --data grant_type=refresh_token \
      --data audience=IAP_CLIENT_ID \
      https://oauth2.googleapis.com/token
```

配文: '`IAP_CLIENT_ID` is the primary client ID used to access your application, and `DESKTOP_CLIENT_ID` and `DESKTOP_CLIENT_SECRET` are the client ID and secret you created when you set up the client ID above'

client\_id 一定是签名所用的服务账号的 ClientID, 那么 IAP\_CLIENT\_ID 到底是什么, 什么是 'primary client ID'

回到 `Credentials` 页面, 除了每个服务账号对应的 Client IDs, 还会有一个系统创建的叫做 IAP-App-Engine-app

![](../.gitbook/assets/image%20%2861%29.png)

在把所有 Client ID 都试过一遍之后, 终于确定了这就是需要填在 subscription 设置里的 audience.

把 IAP-App-Engine-app 对应的 Client ID 填进去就可以让 Sub/Pub 正确推送到目标 GAE 了.

不得不说, 谷歌的文档写的是真的烂.

