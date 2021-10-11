# GCP Pub/Sub push 至 IAP 保护的 Endpoint

众所周知 [IAP](https://cloud.google.com/iap/docs)(Identity Aware Proxy)对 AppEngine 开启后, 所有 GAE 的 service 都需要先通过 OAuth2 鉴权才能访问. 普通用户在浏览器打开 IAP 保护的 GAE 地址就会被跳转到登录页面去登录谷歌账号. 那问题来了, Pub/Sub 的消息如果要推送到一个 IAP 保护的地址应该怎么操作.

创建 subscription 的时候如果选择了 Push 方式, 下面就会有一个 Enable Authentication 的勾选框. 填好 Endpoint(必须得是谷歌可以验证其所属的地址, 比如 GAE) 后再选择一个用来产生 JWT 签名的服务账号. 命令行操作可以看 [https://cloud.google.com/pubsub/docs/push#setting_up_for_push_authentication](https://cloud.google.com/pubsub/docs/push#setting_up_for_push_authentication)

(命令行操作还需要手动给予 Pub/Sub 自动创建的服务账号额外权限, 详见上面的文档链接)

![](<../.gitbook/assets/image (60).png>)

之后就像授权普通用户访问 IAP 那样给 JWT 签名用的服务账号添加 `IAP-secured Web App User` 权限. 然后来试一试.

随便发个消息, 以为已经弄好了, 结果消息一直没有 ACK.

更气人的是看不到 IAP 的访问日志, 日志里只有 IAP 设置的修改.

这一部分日志属于 API 内部日志, 不由用户代码产生, 默认不记录这些日志, 要先去 `Audit Logs` (在网页控制台搜索这一页面)打开 IAP 日志.

![](<../.gitbook/assets/image (61).png>)

在 `Logging` 中按以下 Query 来查询日志

```
resource.type="gae_app"
```

可以在左边的 Log Fields 看到一个 `module_id` 是 null 的东西

![](<../.gitbook/assets/image (65) (1) (1).png>)

这个没有名字的模块就是 IAP 的访问日志, 由于它没有名字不能单独筛选, 只能混在所有 module 的日志里一起看.

然后就会看到一个双感叹号的条目, 赫然写着 'Permission Denied'. 这说明 Pub/Sub 发出的请求没有通过 IAP 验证.

在 StackOverflow 搜索这个问题只有一个结果, 评论区有人提到创建 subscription 时的 Audience 是必填项, 其值为 IAP 自己的 Client ID(不是用于签 JWT 的服务账号的 OAuth2 Client ID) [https://stackoverflow.com/questions/57817374/google-pub-sub-push-message-not-working-for-iap-enabled-app-engine#comment104348926\_58151897](https://stackoverflow.com/questions/57817374/google-pub-sub-push-message-not-working-for-iap-enabled-app-engine#comment104348926\_58151897)

我们回到 IAP 的文档去看 Programmatic Authentication 一节再去看一下 [https://cloud.google.com/iap/docs/authentication-howto#accessing_the_application](https://cloud.google.com/iap/docs/authentication-howto#accessing_the_application)

自己获取 JWT 的 curl 命令是这样的

```bash
curl --verbose \
      --data client_id=DESKTOP_CLIENT_ID \
      --data client_secret=DESKTOP_CLIENT_SECRET \
      --data refresh_token=REFRESH_TOKEN \
      --data grant_type=refresh_token \
      --data audience=IAP_CLIENT_ID \
      https://oauth2.googleapis.com/token
```

配文: '`IAP_CLIENT_ID` is the primary client ID used to access your application, and `DESKTOP_CLIENT_ID` and `DESKTOP_CLIENT_SECRET` are the client ID and secret you created when you set up the client ID above'

`DESKTOP_CLIENT_ID` 就是用来产生 JWT 用的服务账号的 Client ID, 而 `IAP_CLIENT_ID` 是系统给出的 IAP 自己的 Client ID.

回到 [`Credentials`](https://console.cloud.google.com/apis/credentials) 页面, 除了每个服务账号对应的 Client IDs, 还会有一个系统创建 Client 的叫做 IAP-App-Engine-app

![](<../.gitbook/assets/image (63).png>)

把 IAP-App-Engine-app 对应的 Client ID 填到 subscription 的 audience 就可以让 Sub/Pub 正确推送到目标 GAE 了.

不得不说, 谷歌的文档写的是真的烂.
