# 模拟 Bilibili Android 客户端登录

本文撰写时的 Bilibili Android 客户端版本 5.15.0.515000

### 截取数据包

首先, 我们可以确信的一件事情就是, B 站的 APP 通过 RESTFul API 来与服务端交互. 我们对 APP 进行反编译, 就可以看到 APP 中使用了 Okio 中的类, 并且引入了 Retrofit 这个第三方库.

接下去, 我们要对 APP 进行 http/https 截包, 通常对 Android 设备的截包的方案是设置系统代理到 PC, 然后在 PC 上对 nat 中的地址进行截包.

这种方案其实很麻烦, 尤其是当使用虚拟机运行 Android 时. 如果数据量不是很大, 我们可以选择使用提供截包功能的 Android 程序来进行截包, 例如 `Packet Capture` 之类的应用.

现在 B 站大部分 API 都已经替换为 https, 而 https 截包需要安装截包程序提供的 SSL 证书, 从而实现 https 的 MITM.

但是我们很快会发现, 使用截包程序\(无论是手机上运行的 Packet Capture 还是 PC 上运行的 Fiddler 等程序\)去截取 Bilibili 客户端的数据包, 会导致 APP 提示诸如 `电波无法到达呦`, `加载失败了`, `Trust anchor for certification path not found` 等字样.

如下图所示

![](https://user-images.githubusercontent.com/17246126/37017199-a700e284-214a-11e8-88b9-6589afb58e06.png)

![](https://user-images.githubusercontent.com/17246126/37017204-afbd1262-214a-11e8-92ab-9de99e92c837.png)

但是我们截包诸如淘宝, 支付宝等其他应用, 却是正常的.

而这个问题, 实际上是由于 Android 7 新增了 证书固定 功能. 此功能可以使 APP 不使用系统证书列表\(包括自带的根证书列表等\), 而仅使用 APP 自定义的证书链.

有关这个功能的详情请见 [https://developer.android.com/training/articles/security-config](https://developer.android.com/training/articles/security-config)

绕过这个功能的办法有两个, 反编译 APK 并修改 xml 再编译回去, 或者使用 Android 6 版本及以下的 Android 镜像.

### 分析数据包

现在我们成功截取到了数据包, 我们来看一个典型的数据包的结构

```text
GET /AppRoom/index?_device=android&amp;_hwid=JxdyESFAJkcjEicQbBBsCTlbal5uX2Y&amp;access_key=cb93fb8cc20b2d3245f9ea824130ac21&amp;appkey=1d8b6e7d45233436&amp;build=515000&amp;buld=515000&amp;jumpFrom=24000&amp;mobi_app=android&amp;platform=android&amp;room_id=3151254&amp;scale=xxhdpi&amp;src=google&amp;trace_id=20171012145800040&amp;ts=1507791520&amp;version=5.15.0.515000&amp;sign=0ad8bd04c480714075b57e04aff2e8d3 HTTP/1.1
Display-ID: 20293030-1507791479
Buvid: JxdyESFAJkcjEicQbBBsCTlbal5uX2Yinfoc
User-Agent: Mozilla/5.0 BiliDroid/5.15.0 (bbcallen@gmail.com)
Device-ID: JxdyESFAJkcjEicQbBBsCTlbal5uX2Y
Host: api.live.bilibili.com
Connection: Keep-Alive
Accept-Encoding: gzip
```

有三个 Params 我们是不清楚的, 分别是 `access_key`, `appkey`, `sign`

我们重复请求这一 API, 我们发现, `appkey`\(1d8b6e7d45233436\) 和 `access_key` 每次都是一样的.

我们退出客户端的登录状态后再次请求, 我们发现请求中没有了 `access_key`. 我们再次登录, 此时 `access_key` 与上一次登录不一样了. 这说明 `access_key` 就是 token.

也就是说这是一个典型的 Token 登陆场景, 我们只要从身份服务器获得一个 Token, 就可以用它访问所有 API.

而 `sign` 势必是通过一种校验算法得到的校验码, 用于防止伪造请求.

起初, 我研究了很久也没有猜到 `sign` 的生成算法, 直到有一天我看到了这篇文章 [http://www.jianshu.com/p/5087346d8e93](http://www.jianshu.com/p/5087346d8e93)

出于安全性问题, appSecret 保存在 so 文件中, 通过 jni 调用. 关于反编译 B 站客户端中的 so 文件来得到 appSecret 的文章我之前看过, 但是现在一时找不到了, 如果那篇文章有人见过, 麻烦补个链接.

### sign 生成算法

这里简要描述一下 `sign` 的生成过程.

首先将 Params 的 Name-Value 对按 Name 的字典序排列, 变为如下字符串

```text
key1=value1&key2=value2&key3=value3
```

然后再拼接上 Android APP 内置的 appSecret\(仅拼接值\)

```text
key1=value1&key2=value2&key3=value3ea85624dfcf12d7cc7b2b3a94fac1f2c
```

最后对以上字符串进行 md5 加密, 就得到了 sign.

得到 `sign` 之后, 将 `sign` 作为请求的最后一个 Param.

整条 Params 差不多类似这样

```text
key1=value1&key2=value2&key3=value3&sign=302d7fd77cd91c5ac530f6bad109a3dd
```

### 固定参数

我们注意到, 各个 API 里面, 都有一大堆的固定参数. 这些参数是用 OkHttpClient 的拦截器加上去的, 所以每个请求都有. 下面给出他们的含义

```text
_device 固定值, 一定为 "android"
_hwid 每台手机固定的硬件编码
build `version`的最后一节
mobi_app 固定值 android
platform 固定值 android
scale 手机屏幕的dpi, 现在的大屏手机都是 "xxhdpi"
trace_id 表示时间的字符串, 纯数字. 格式(注意秒前有三个零): $年$月$日$时$分000$秒
ts 当前的 Unix Timestamp
version 每客户端版本号
```

这些固定参数并非是 API 请求必须的, 大部分时候仅用于服务端统计. 但是需要注意的是, 确实有少数 API 会使用到这些固定参数里面的一个或多个, 所以在模拟请求时最好全部带上.

### 登陆接口

现在我们知道了各个固定参数的含义, 还知道了 sign 算法, 只要通过登陆接口, 获取 access\_key, 我们就可以访问所有 API 了.

我们在客户端登陆时进行截包, 发现登陆接口的地址在这里

```text
https://passport.bilibili.com/api/oauth2/login
```

参数有以下几个\(不考虑固定参数\)

```text
appkey
username
password
sign
```

`appkey` 我们之前已经知道了, 而 `username` 是明文传输的, 关键就是这个 `password`.

`password` 是用密文传输的.

`password` 的密文, 乍一看十分眼熟, 十分类似 Bilibili Web 版登陆时传输的密文 `password`.

后来我们确信, Android 客户端的 `password` 加密算法与 Web 版是一样的. 并且很巧的是, Web 版的 `password` 加密算法我之前已经研究过了.

在 Web 版中, 前端 js 会访问 GET [https://passport.bilibili.com/login?act=getkey](https://passport.bilibili.com/login?act=getkey) 来获得一个 `hash` 值和 B 站的 RSA 公钥.

我们翻看 Android 客户端前后的请求记录, 发现 Android 访问 POST [https://passport.bilibili.com/api/oauth2/getKey](https://passport.bilibili.com/api/oauth2/getKey) 来获得 `hash` 和 RSA 公钥.

请求的返回值是这样的

```json
{
    "ts": 1536261900,
    "code": 0,
    "data": {
        "hash": "0e7d998fb519dc0c",
        "key": "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCdScM09sZJqFPX7bvmB2y6i08J\nbHsa0v4THafPbJN9NoaZ9Djz1LmeLkVlmWx1DwgHVW+K7LVWT5FV3johacVRuV98\n37+RNntEK6SE82MPcl7fA++dmW2cLlAjsIIkrX+aIvvSGCuUfcWpWFy3YVDqhuHr\nNDjdNcaefJIQHMW+sQIDAQAB\n-----END PUBLIC KEY-----\n"
    }
}
```

密码加密算法大致是这样的:

将 `hash` 值与明文密码做字符串拼接, 即 "$hash+$password"

将得到的结果字符串, 用 RSA 公钥加密, 得到密文密码\(如果语言标准库输出的是 ByteArray 则进行一次 Base64\).

### 模拟登陆

我们已经知道了登陆接口, 并且已经知道了所有参数的生成算法, 现在我们就来试一试.

本来以为已经胜券在握, 但是服务器却返回了一个错误

![](../.gitbook/assets/image%20%2823%29.png)

\(现在 B 站有了新的登陆接口, 旧的接口现在百分百要求验证码, 这张图是现在的截图, 已经处理了验证码问题\)

服务器提示 `can't decrypt rsa password~`

我们首先想到的是, 是不是我们的 sign 算法是错的, 于是我们改动 sign 的值, 使其变为错误的.

![](../.gitbook/assets/image%20%286%29.png)

这时服务器提示 `API sign invalid`

这说明, 我们的 sign 算法一定是正确的, 否则请求将在 password 密文解密前就被服务器返回.

我们使用一个错误的用户名进行尝试

![](../.gitbook/assets/image%20%2814%29.png)

这时服务器返回 "账号或者密码错误".

我们再用错误的密码进行尝试

![](../.gitbook/assets/image%20%2811%29.png)

此时依然显示 `can't decrypt rsa password~`

现在我们可以推测服务端的代码逻辑了, 大概是这样的

```kotlin
if(!checkSign(queryString, sign)) {
    throw SignInvalidException()
}

val userEntity = userRepository.findByUsername(username)?:throw UsernameOrPasswordIncorrectException(username)

return try {
    decryptPassword(cryptPassword)
} catch (e : Exception) {
    throw CannotDecryptRSAPasswordException(e)
}.takeIf { it == userEntity.password }  //假设数据库存储的是明文密码
?.ResponseEntity.ok().build()
?:throw UsernameOrPasswordIncorrectException(username)
```

所以我们无法登陆, 一定是由于我们的密文密码被解密后, 与明文密码不一致.

那么, 是不是密码加密算法错了? 也不是. 因为如果我们对真实的 Android 客户端发出的请求进行重放, 也会收到 `can't decrypt rsa password~`

我们在 APP 上进行多次登陆尝试, 试着比对每一次的参数不同, 我们发现, 每一次的 password 密文, 都是不一样的. 这时我们才猛然意识到, 最开始获取的那个 hash 值, 是会变化的.

B 站正是使用这段会变化的 hash 拼接到明文密码前面, 来保证每次加密出来的密文密码都不一样, 从而避免了 API 猜解.

而这段 hash 的长度是固定的, 所以密码解密后, 可以得到 hash + 明文密码 两段.

所以问题一定出在这个 hash 上.

那么 hash 会有什么问题呢, 答案是时效性.

这段 hash 不是随便生成的, 从这个 hash 可以逆推出 hash 的生成时间\(具体算法不明\). 也就是说, 服务端会首先验证 hash 表达的时间与收到请求的时间是否在一定间隔内, 这种手法经常被用来阻止通过重放请求来进行的 API 猜解.

因此我们必须在 hash 失效前完成登陆过程\(大概是十秒\), 所以手动发送请求永远也登陆不了.

那么我们使用代码来实现这个登陆过程

![](../.gitbook/assets/image%20%284%29.png)

登陆成功后, 服务器返回

```json
{
  "code": 0,
  "data": {
    "access_token": "3a1b3f690a111768fd2f26da06357243",
    "refresh_token": "8f361851b9866f3877c303f0ef4ef067",
    "mid": 20293030,
    "expires_in": 2592000
  },
  "ts": 1536262532
}
```

其中 `refresh_token` 是 OAuth2 中的 refreshToken, 刷新 token 时使用.

而 `access_token`, 就是我们梦寐以求的 access\_key.

### 调用 API

有了 token, 我们现在可以调用各种 API 了, 比如说获取自己所关注的主播列表

![](../.gitbook/assets/image%20%2818%29.png)

Bilibili 的 API 有很多很多, 这里就不细讲了, 感兴趣的同学可以去这个仓库看看\(没错, 我真的把这个坑开了\) [https://github.com/czp3009/bilibili-api](https://github.com/czp3009/bilibili-api)

有了 token, 我们就可以为所欲为了, 小伙伴们欢呼雀跃!
