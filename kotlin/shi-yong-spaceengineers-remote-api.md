# 在 Kotlin 使用 SpaceEngineers Remote API

众所周知, [SpaceEngineers](https://store.steampowered.com/app/244850/Space_Engineers/) 的服务端\(vanilla, 非 [torch](https://torchapi.net/)\)上那个查看服务器内游戏信息的玩意是通过网络来获取信息的\(localhost\). 实际上这个东西有独立版本应用程序, 也在服务端根目录下, 叫做 `VRageRemoteClient` . VRage 是 SpaceEngineers 使用的游戏引擎, 这个游戏引擎本身也是 Keen 开发的.

于是在谷歌上搜索 SpaceEngineers API, 只能找到这个页面 [https://www.spaceengineersgame.com/dedicated-servers.html](https://www.spaceengineersgame.com/dedicated-servers.html) 并且介绍 API 有哪几个的, 只有一张图 [http://mirror.keenswh.com/images/remoteApi.png](http://mirror.keenswh.com/images/remoteApi.png) . 页面下方有一段 c\# 代码用于示例, 但是有些东西是 .Net 平台特定的, 在其他语言可能有麻烦.

示例代码是这样的

```text
private readonly string m_remoteUrl = "/vrageremote/{0}";

public RestRequest CreateRequest(string resourceLink, Method method, 
params Tuple<string, string>[] queryParams)
{
    string methodUrl = string.Format(m_remoteUrl, resourceLink);
    RestRequest request = new RestRequest(methodUrl, method); 
    string date = DateTime.UtcNow.ToString("r", CultureInfo.InvariantCulture);
    request.AddHeader("Date", date); 
    m_nonce = random.Next(0, int.MaxValue);
    string nonce = m_nonce.ToString();
    StringBuilder message = new StringBuilder();
    message.Append(methodUrl); 
    if (queryParams.Length > 0)
    {
        message.Append("?");
    }

    for (int i = 0; i < queryParams.Length; i++)
    {
        var param = queryParams[i];
        request.AddQueryParameter(param.Item1, param.Item2);
        message.AppendFormat("{0}={1}", param.Item1, param.Item2);
        if (i != queryParams.Length - 1)
        {
            message.Append("&");
        }
    }

    message.AppendLine();
    message.AppendLine(nonce);
    message.AppendLine(date);
    byte[] messageBuffer = Encoding.UTF8.GetBytes(message.ToString());

    byte[] key = Convert.FromBase64String(m_securityKey);
    byte[] computedHash;
    using (HMACSHA1 hmac = new HMACSHA1(key))
    {
        computedHash = hmac.ComputeHash(messageBuffer);
    }

    string hash = Convert.ToBase64String(computedHash);
    request.AddHeader("Authorization", string.Format("{0}:{1}", nonce, hash));
    return request;
}
```

可以看出, 最终的请求必须有至少以下两个 header

```text
Date: httpDateString()
Authorization: "$nonce:$hash"
```

在示例代码中, 我们可以看到 c\# 中是这样得到 Date 的:  `DateTime.UtcNow.ToString("r", CultureInfo.InvariantCulture)` , c\# 在输出时间字符串的时候会根据不同的 culture\(fr, de, cn...\) 来得到不同的结果, 而 `InvariantCulture` 用于得到统一的结果. 它的输出是这样的

```text
Tue, 15 May 2012 16:34:16 GMT
```

这其实就是平时俗称的 `HttpDateString` , 在不同的语言中根据这个格式来格式化时间即可, 例如在 Java 中

```java
DateTimeFormatter
    .ofPattern("EEE, dd MMM yyyy HH:mm:ss z")
    .withLocale(Locale.US)
    .withZone("GMT")
```

`Authorization` 由两部分组成, `nonce` 就是一个大于 0 的 Int 类型随机数. 而这个 `hash` 就很有来头了, hash 的值是把 url\(包含 query params\), nonce, date 用 `StringBuilder.AppendLine` 拼接在一起然后做一次 `HmacSHA1` , 用到的 key 就是 VRageRemoteClient 上需要填入的 `Security Key`. .Net 的 `AppendLine` 是平台相关的, 所以使用的是 `\r\n`

```kotlin
val nonce = Random.nextInt(0..Int.MAX_VALUE)
val date = Instant.now().toHttpDateString()
val message = "$url\r\n$nonce\r\n$date\r\n"
val hash = Mac.getInstance("HmacSHA1").apply {
    init(secretKey)
}.doFinal(message.toByteArray()).let {
    Base64.getEncoder().encodeToString(it)
}
```

如果想要百分百还原, 还可以加上 UA

```kotlin
userAgent("RestSharp/106.6.10")
```

好了, 我们现在知道如何发送一个合法的请求了. 不过有些东西网页上没有讲到, 以下记录一些遇到的坑.

send message\(POST /v1/session/chat\) 是唯一一个 BODY 有内容的请求, 需要特别注意的是, BODY 应该是这样的

```text
"This is a message"
```

**它  自  身  包  含  引  号!**

get message\(GET /v1/session/chat\) 有一个可选参数\(Query Param\)名称为 `Date` , 值是一个 `DateTime.Ticks` \(最后转换为 Long 类型\), 用于控制服务器返回的消息\(复数\)从哪个时间之后开始\(闭区间\).

如果没有这个参数, 服务器将返回所有聊天消息. 聊天消息中包含 `timestamp` 字段, 最后一条消息的这个字段的值加一, 就可以作为下一次请求聊天消息时所用的 `Date` 的值.

c\# 的 `DateTime.Ticks` 是一个从 0001-01-01T00:00:00.00Z 开始以 100毫秒 为时间间隔递增的数字. 如果要对这个时间进行处理, 就需要转换为对应语言中的时间类型. 以 Kotlin 为例

```kotlin
//c# ticks
internal typealias Ticks = Long

private val offset = Duration.between(
    Instant.parse("0001-01-01T00:00:00.00Z"),
    Instant.ofEpochSecond(0)
).seconds
private val zoneOffset = ZoneOffset.ofTotalSeconds(TimeZone.getDefault().rawOffset / 1000)

fun Ticks.toLocalDateTime() =
    LocalDateTime.ofEpochSecond(
        this / 10_000_000 - offset,
        0,
        zoneOffset
    )!!
```

banned players 和 kicked players 这两个 API 返回的列表总是空的, 不太明确原因.

之前把 SpaceEngineers Remote API 完整的用 Kotlin 实现了一遍 [https://github.com/czp3009/space-engineers-remote-api](https://github.com/czp3009/space-engineers-remote-api)

然后又试着写了一个可以在手机上管理服务器的 Android App [https://github.com/czp3009/SpaceEngineersRemoteClient](https://github.com/czp3009/SpaceEngineersRemoteClient)

不过这个 APP 有很多问题, 重构又懒得弄, 你猜我能咕咕咕到什么时候

