# 模拟超星网课 Android 客户端

本文撰写时的 `学习通` 版本 4.0.1

这个 `超星` 有好几个名字, `慕课`, `尔雅通识课`, `泛雅`, Android APP 叫 `学习通`.

虽然这玩意有那么多名字, 但是有一件事情是亘古不变的, 那就是国内大量学校使用这个网课平台来填充学生的课程表. 而这家网课平台的课程质量普遍不佳, 分辨率低, 教师口音重, 教授内容废话连篇, 所以我们就想到了, 能不能不看这些网课呢.

这家网课平台的 Web 端的视频播放器是一个 Flash, 监听用户鼠标事件, 一旦用户鼠标移出 Flash 区域或者将浏览器最小化, 都将暂停视频直到鼠标重新放上. 而 Android 客户端就更是精彩绝伦, 视频播放的那个 Activity 直接是一个 WebView, 同样是通过 js 来监听切换到后台的事件, 令人啧啧称奇.

我们今天就来探究一下这个 `超星` 的 Android APP 是怎么运作的.

## 正常登陆

首先我们得能登陆. 我们下载一个 `学习通` 看一下正常使用是怎么登陆的.

感兴趣的同学可以去这里下载一个 [https://app.chaoxing.com](https://app.chaoxing.com)

其实这个 APP 在我还在使用 MI3 时是用过的, 唯一的体会就是相当卡, 卡到鸡巴旋转脱落. 甚至是 ListView 的滚动都能掉帧的那种卡顿, 恐怕放眼全球也只有 12306 的 APP 能与之相抗衡.

APP 一打开是这样的

![](../.gitbook/assets/image%20%2819%29.png)

看到下面这条非常类似 IONIC 的抽屉, 起初以为是 H5 APP, 但是看了一下各种动画效果发觉并不是.

点击下方抽屉里的 `我的` 按钮

![](../.gitbook/assets/image%20%2853%29.png)

然后点击 `请先登陆`

![](../.gitbook/assets/image%20%2842%29.png)

如果我们不输入用户名直接点 `登陆` 甚至还会有一个 Toast 来提醒用户必须输入用户名. 这个 APP 竟然有本地表单验证实在是难以置信.

## 截包

我们马上来截包一下, 当我们登陆的时候, APP 会发出这样的一个数据包\(MultipartForm\)

```text
POST /v11/loginregister?token=4faa8662c59590c6f43ae9fe5b002b42&_time=1537463981249&inf_enc=7e0a0c15d58556a991eb94011ac16cc4 HTTP/1.1
Accept-Language: zh_CN
Cookie: 
Content-Length: 720
Content-Type: multipart/form-data; boundary=P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ
Host: passport2-api.chaoxing.com
Connection: Keep-Alive
User-Agent: Dalvik/2.1.0 (Linux; U; Android 8.0.0; MI 6 MIUI/8.9.13) com.chaoxing.mobile/ChaoXingStudy_3_4.0.1_android_phone_287_2 (@Kalimdor)_-30877643

--P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ
Content-Disposition: form-data; name="uname"
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

13902095201
--P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ
Content-Disposition: form-data; name="code"
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

19951118
--P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ
Content-Disposition: form-data; name="loginType"
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

1
--P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ
Content-Disposition: form-data; name="roleSelect"
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

true
--P3LxQlvmN5TjoopArsDJl0scmxQ3pjpqvQ--
```

Params 有三个, `token`, `_time`, `inf_enc`.

`_time` 我们一眼就可以确定是时间戳.

通过多次登录, 我们发现 `token` 每次都是一模一样的, 说明这是类似 `appKey` 一样的固定值.

那么剩下就是 `inf_enc` 的问题. 这个参数每次都是不一样的, 所以应该是某种签名算法.

根据他的长度和只有数字和小写字母推测可能是 `MD5` 加密得到的密文, 通过现有的一些反查网站查询无果. 试验了几个可能的明文排列方案也与最终的结果不符.

于是我们决定通过反编译来获得 `inf_enc` 的生成算法.

## 反编译

为了更好地阅读源码, 我们将 `jadx` 反编译出的源码用 `IDEA` 打开.

根据多年开发经验, 通常国内的 Android 程序员很喜欢著名代码默写器 `Eclipse` 与早已停止维护多年的的 `ADT` 插件. 所以并不会被提示代码缺陷, 其中就包括硬编码问题.

所以我们只需要简单地搜索程序中出现的一些文本, 就可以找到对应的代码所在的位置.

我们在未输入用户名的情况下点击 `登录` 按钮会被提示 `请输入你的登录账号`, 这段文本一定在这个按钮的监听器中, 我们搜索它.

![](../.gitbook/assets/image%20%289%29.png)

搜索结果有两个, 而第二个结果里面有什么 验证码 之类的东西, 我们在 Android 客户端登录的时候是没有验证码的, 所以一定不是这个.

在类 `com.chaoxing.mobile.account.d`\(已混淆\) 中我们找到了一个 ClickListener

```java
private OnClickListener q = new OnClickListener() {
    public void onClick(View view) {
        if (!CommonUtils.isFastClick()) {
            int id = view.getId();
            if (id == R.id.tv_left) {
                d.this.getActivity().onBackPressed();
            } else if (id == R.id.tv_right) {
                d.this.d();
            } else if (id == R.id.iv_clear_account) {
                d.this.e.setText("");
                d.this.c();
                d.this.e.requestFocus();
                d.this.showSoftInput(d.this.e);
            } else if (id == R.id.tv_forget_password) {
                d.this.f();
            } else if (id == R.id.btn_login) {
                d.this.l();
            } else if (id == R.id.tv_sign_up) {
                d.this.e();
            } else if (id == R.id.tv_login_by_phone) {
                d.this.g();
            } else if (id == R.id.tv_login_by) {
                d.this.i();
            }
        }
    }
};
```

从布局 XML 中确认了 `btn_login` 就是那个登录按钮.

我们来看一下这个 `l()` 方法

```java
private boolean l() {
    e.a();
    String trim = this.e.getText().toString().trim();
    String obj = this.g.getText().toString();
    if (y.c(trim)) {
        aa.a(getActivity(), "请输入你的登录账号");
        this.e.requestFocus();
        return false;
    } else if (y.c(obj)) {
        aa.a(getActivity(), "请输入你的密码");
        this.g.requestFocus();
        return false;
    } else {
        hideSoftInput();
        a(trim, obj, 1);
        return true;
    }
}
```

就是那段文本所在的方法, 如果用户名和密码都被输入过的话, 将执行 `a(trim, obj, 1)`, 而这个方法是这样的

```java
//login
private void a(String username, String password, int loginType) {   //loginType is always 1
    getLoaderManager().destroyLoader(4097);
    try {
        MultipartEntity multipartEntity = new MultipartEntity();
        multipartEntity.addPart("uname", new StringBody(username, Charset.forName("UTF-8")));
        multipartEntity.addPart("code", new StringBody(password, Charset.forName("UTF-8")));
        username = com.chaoxing.mobile.unit.a.b.l;  //loginType
        StringBuilder stringBuilder = new StringBuilder();  //can be replaced with String
        stringBuilder.append(loginType);
        stringBuilder.append("");
        multipartEntity.addPart(username, new StringBody(stringBuilder.toString(), Charset.forName("UTF-8")));
        multipartEntity.addPart("roleSelect", new StringBody("true", Charset.forName("UTF-8")));    //hard coded
        Bundle bundle = new Bundle();
        bundle.putString("apiUrl", g.bq()); //g is passwordEditText(R.id.et_password)
        getLoaderManager().initLoader(4097, bundle, new a(multipartEntity));
        this.m.setVisibility(0);
    } catch (Throwable e) {
        com.google.a.a.a.a.a.a.b(e);    //Toast
    }
}
```

\(为了方便阅读, 一些变量已经人工反混淆并添加了一些注释, 下同\)

这里构造了一个 `MultipartEntity` \(来自 Apache HttpClient\), 而其内容就是我们登陆的时候, 发送的那个 MultipartForm.

`uname` 就是 用户名

`code` 就是 密码\(明文, 对, 你没听错, 明文\)

`loginType` 实际上是硬编码的, `a(String username, String password, int loginType)` 只在一个地方被调用, IDEA 提示其值永远为 1.

`roleSelect` 也是硬编码的, 值永远为 "true"

从这里我们已经得知了整个 MultipartForm 的生成算法.

然后我们继续看, 这个 `MultipartEntity` 被构造后, 被用于构造一个 `LoaderCallbacks`.

我们看一下这个 `LoaderCallbacks` 在哪里, 然后惊人的发现, 还是他妈在这个类里面\(内部类\). 将 '把所有东西写在一个文件' 的作风表现到了极致.

```java
/* compiled from: TbsSdkJava */
private class a implements LoaderCallbacks<Result> {
    private MultipartEntity multipartEntity;
    private String c;

    public void onLoaderReset(Loader<Result> loader) {
    }

    a() {
    }

    a(MultipartEntity multipartEntity) {
        this.multipartEntity = multipartEntity;
    }

    a(String str) {
        this.c = str;
    }

    public Loader<Result> onCreateLoader(int i, Bundle bundle) {
        if (i != 4097) {
            return null;
        }
        Loader dataLoaderWithLogin = new DataLoaderWithLogin(d.this.getContext(), bundle, this.multipartEntity);
        dataLoaderWithLogin.setOnCompleteListener(d.this.u);
        return dataLoaderWithLogin;
    }

    /* renamed from: a */
    public void onLoadFinished(Loader<Result> loader, Result result) {
        d.this.getLoaderManager().destroyLoader(loader.getId());
        if (loader.getId() == 4097) {
            d.this.a(result);
        }
    }
}
```

我们之前的 `MultipartEntity` 被传入了一个叫做 `DataLoaderWithLogin` 的类里面. 这个类在包 `com.fanzhou.loader.support` 中, 似乎是什么工具类.

我们在这个类中没有找到有关 `inf_enc` 或者 `_time` 的字眼, 这个类似乎是一个通用工具类.

我们尝试在 Github 搜索 `fanzhou` 一词, 没有找到有效结果, [https://github.com/fanzhou](https://github.com/fanzhou) 这名用户甚至一个仓库都没有.

我们在 mvnrepository 搜索 `com.fanzhou` 一词也没有搜索到任何结果.

最后我们在 Google 搜索 fanzhou.com, 我们惊异的发现, 真的有这个网站. 这是一个连 HTTPS 都没有的网站 [http://www.fanzhou.com/index.html](http://www.fanzhou.com/index.html)

网站的首页是一个 Chrome 早已默认不加载的 Flash, 以及一些资源不存在的图片. 甚至还能看到著名垃圾站生成工具 '织梦CMS' 的名字.

我们推测, 这个包可能是超星的某个雇员写的, 并用自己的域名作为其包名.

既然这个类是通用工具类, 而里面并没有 `inf_enc` 相关内容, 说明这个参数应该是用某种拦截器加上去的.

我们直接搜索 `inf_enc`, 找到了类 `com.fanzhou.util.ac`

```java
private static String b() {
    StringBuilder stringBuilder = new StringBuilder();
    stringBuilder.append("token=");
    stringBuilder.append("4faa8662c59590c6f43ae9fe5b002b42");
    stringBuilder.append("&_time=");
    stringBuilder.append(System.currentTimeMillis());
    return stringBuilder.toString();
}

private static String f(String str) {
    return d("Z(AfY@XS", str);
}

private static String d(String str, String str2) {
    StringBuilder stringBuilder = new StringBuilder();
    stringBuilder.append(str2);
    stringBuilder.append("&DESKey=");
    stringBuilder.append(str);
    str = m.b(stringBuilder.toString());
    stringBuilder = new StringBuilder();
    stringBuilder.append(str2);
    stringBuilder.append("&inf_enc=");
    stringBuilder.append(str);
    return stringBuilder.toString();
}
```

举头一看还能看到 `token` 和 `_time` 字眼.

这个 `token` 确实是硬编码的, 永远为固定值 `4faa8662c59590c6f43ae9fe5b002b42`

`_time` 为当前时间戳\(不是 UnixTimeStamp, 是带毫秒的\)

我们看到其中还有什么 `DESKey` 之类的东西, 难道这居然是 `DES 加密`, 后来我们知道, 我们不出所料的高估了超星程序员.

我们来看一下其中的 `m.b(String)` 是个什么方法. 它在另一个工具类 `com.fanzhou.util.m` 里面.

```java
public static String b(String str) {
    if (!(str == null || str.length() == 0)) {
        try {
            MessageDigest instance = MessageDigest.getInstance("MD5");
            instance.update(str.getBytes());
            byte[] digest = instance.digest();
            StringBuffer stringBuffer = new StringBuffer("");
            for (int i : digest) {  //i is nerver used
                int i2;
                if (i2 < 0) {
                    i2 += 256;
                }
                if (i2 < 16) {
                    stringBuffer.append("0");
                }
                stringBuffer.append(Integer.toHexString(i2));
            }
            return stringBuffer.toString();
        } catch (Throwable e) {
            a.b(e); //Toast
        }
    }
    return null;
}
```

这里我们看到, 它对传入的字符串进行了 `MD5` 加密, 然后对结果 byte\[\] 进行了一次遍历处理, 但是不知道为何, 反编译结果中的 i2 变量并没有被初始化.

现在我们暂时还不知道他是怎么样一个处理过程, 我们先将这些方法拷贝出来, 运行一下.

![](../.gitbook/assets/image%20%2826%29.png)

运行到此处时, `m.b(String)` 的传入值为

```text
token=4faa8662c59590c6f43ae9fe5b002b42&_time=1537538011800&DESKey=Z(AfY@XS
```

![](../.gitbook/assets/image%20%2834%29.png)

而运行到此处, 也只是简单的把传入的字符串给 `MD5` 加密了一下.

最终程序的输出为

```text
token=4faa8662c59590c6f43ae9fe5b002b42&_time=1537538011800&inf_enc=00000000000000000000000000000000
```

我们回过头来看一下那个循环的内容

```java
for (int i : digest) {
    int i2;
    if (i2 < 0) {
        i2 += 256;
    }
    if (i2 < 16) {
        stringBuffer.append("0");
    }
    stringBuffer.append(Integer.toHexString(i2));
}
```

循环一开始判断了 `i2` 的值是不是小于 0, 如果是, 则加 256 后再将其十六进制值\(两位\)拼接到字符串最后.

如果 `i2` 小于 16 则先在字符串最后拼接 0 再拼接其十六进制值\(一位\).

我们去 jadx 再次确认一下这个变量到底是怎么回事.

![](../.gitbook/assets/image%20%2830%29.png)

我们发现这一行没有行号, 说明这里有编译器优化.

那么, 这个 `i2` 只能是 `i` 赋值过去的临时变量.

我们修改一下拷贝出来的代码, 再把时间戳的值改为我们截获的数据包中的时间戳的值, 再次运行.

![](../.gitbook/assets/image%20%2815%29.png)

还记得我们截获的数据包中的 `inf_enc` 的值么, 没错, 就是这个. 我们终于破解了 `inf_enc` 的生成算法.

我们整理一下这个算法, 差不多是这样的\(32 位 MD5\)

```kotlin
fun main(args: Array<String>) {
    "token=4faa8662c59590c6f43ae9fe5b002b42&_time=${System.currentTimeMillis()}&DESKey=Z(AfY@XS"
            .md5()
            .run(::println)
}

private fun String.md5() =
        MessageDigest.getInstance("MD5")
                .digest(toByteArray())
                .joinToString(separator = "") {
                    String.format("%02x", it)
                }
```

最后打印出来的就是 `inf_enc`.

## 模拟登陆

现在, 我们来试一试.

![](../.gitbook/assets/image%20%283%29.png)

接着我们得到了一大堆的 `Cookie`, 这些就是我们的凭证\(有效期为一个月\).

我们观察到, APP 在登陆后会立即访问一个地址来获取个人信息, 即 UserInfo

![](../.gitbook/assets/image%20%2822%29.png)

很好, 我们成功的获得了用户信息.

\(这一操作同时也会增加不少 `Cookie`, 所以这是必要操作\)

\([https://passport2.chaoxing.com/api/cookie](https://passport2.chaoxing.com/api/cookie) 也可以获得所有缺失的 `Cookie`\)

\(`Cookie` 有很多个, 一些 `Cookie` 标识用户是哪个学校的, 一些 `Cookie` 标识用户可以使用哪些 API, 相当繁杂\)

## 调用接口

既然我们已经成功登陆了, 现在我们来调用一些需要登陆才能调用的接口.

![](../.gitbook/assets/image%20%2851%29.png)

比如说这个接口可以获取自己需要看的网课的列表.

同样的, 我们可以获得课程里的章节列表, 单元测验等数据.

## 模拟看网课

模拟登陆只是第一步, 我们的征程是模拟看网课.

为了模拟看网课, 我们得先搞清楚, 在正常的看网课操作下, 客户端会发送什么东西到服务端.

除了加载那些 HTML, CSS, JS\(视频播放器是一个 WebView\), 以及 字幕, 副标题 之类的东西. 还会访问一个很特别的 API

```text
GET /richvideo/initdatawithviewer?&start=0&mid=5732900763131425521382549&view=json HTTP/1.1
Host: mooc1-api.chaoxing.com
```

返回的内容是这样的

```javascript
[
    {
        "datas": [
            {
                "memberinfo": "67b120e66d29a7429c6b10df36fba5261ef8397f3648a99c",
                "resourceId": 447037,
                "answered": false,
                "errorReportUrl": "http://mooc1-api.chaoxing.com/question/addquestionerror",
                "options": [
                    {
                        "isRight": false,
                        "name": "A",
                        "description": "互帮互助"
                    },
                    {
                        "isRight": false,
                        "name": "B",
                        "description": "全力救助有困难学生"
                    },
                    {
                        "isRight": true,
                        "name": "C",
                        "description": "尊重困难学生的人格"
                    },
                    {
                        "isRight": false,
                        "name": "D",
                        "description": "看不起困难学生"
                    }
                ],
                "description": "马加爵事件告诉了我们一个在人与人相处过程中的什么道理？",
                "validationUrl": "/richvideo/qv",
                "startTime": 759,
                "endTime": 0,
                "questionType": "单选题"
            }
        ],
        "style": "QUIZ"
    }
]
```

视频中间 "插播" 的那些提问, 不是远端判题的, 而是本地判题的. 这些题目的答案, 在视频一开始, 就已经知道了.

所以我们实际上并不需要处理视频中间的这些提问.

在视频播放的过程中, 我们会看到 APP 在不停的发送一些数据, 仔细一看, 这些数据每分钟都会发送一次.

首先是这么一个 API

```text
GET /multimedia/log/78c415169c17d665ded62ee3c342707a?otherInfo=nodeId_105091689&playingTime=565&duration=819&akid=null&jobid=1425521382863&clipTime=0_819&clazzId=2369933&objectId=54f7b40d53706e35b9f25898&userid=58973666&isdrag=0&enc=7abeb4fdb90e10b7bea7e64d334dc5c8&dtype=Video&view=json HTTP/1.1
Host: mooc1-api.chaoxing.com
```

Params 有这么多

| key | value |
| :--- | :--- |
| otherInfo | nodeId\_105091689 |
| playingTime | 565 |
| duration | 819 |
| akid | null |
| jobid | 1425521382863 |
| clipTime | 0\_819 |
| clazzId | 2369933 |
| objectId | 54f7b40d53706e35b9f25898 |
| userid | 58973666 |
| isdrag | 0 |
| enc | 7abeb4fdb90e10b7bea7e64d334dc5c8 |
| dtype | Video |
| view | json |

这个其实就是心跳包, 我们可以观察到, 每一个心跳包里面的 `playingTime` 参数都会递增 60.

所以 `playerTime` 就是当前播放器时间.

而 `duration` 就是视频的总时长.

当 `playingTime` 与 `duration` 差异很大时, 服务器将返回这样的数据

```javascript
{
    "isPassed": false
}
```

而这两个值相差比较近时\(具体要多近不明确\), 返回内容中的 `isPassed` 将为 true.

也就是说, 我们在看视频的过程中, 会一直向服务器发送这个数据, 服务器正是通过判断实际提交的时间间隔与提交的 `playerTime` 的间隔是否差异过大来判断有没有作弊的.

这也是为什么很多什么直接拖动学习通视频进度条的工具直接让使用者被判定为作弊, 从而失去了学分的原因.

心跳包意味着挂机不可避免, 即使并非是真人在挂机.

\(心跳包在 `playingTime` 为 0 时开始发送, 在视频结束时会额外发送一次\)

心跳包中的 `enc` 每次都会变化, 这个参数也是一种签名算法, 不过比较复杂, 它的生成算法是这样的

```kotlin
"[$clazzId][$userid][$jobid][$objectId][${playingTime * 1000}][d_yHJ!\$pdA~5][${duration * 1000}][$clipTime]"
        .md5()
        .run(::println)
```

除了这个心跳包是一分钟发送一次的, 还有一个请求也是一分钟发一次的.

```text
GET /api/monitor-version?uid=58973111&version=1536923631314&view=json HTTP/1.1
Host: passport2-api.chaoxing.com
```

这个请求每一次发送时的参数都是一模一样的, 而且服务端返回内容也是一模一样的, 都为

```javascript
{
    "status": true
}
```

我们注意到, 这个请求不是发给 api 站的, 而是发给 passport 站的. 那么这意味着, 这个数据包与账户本身有关系.

超星网课实际上还有一个常见的作弊查处理由, 多 IP 同时登陆.

所以这个请求, 实际上是为了让超星服务端判断用户有没有在多 IP 同时看网课, 这会让一部分用淘宝人工代挂的用户翻车.

所以事情变得很危险, 这个请求只能在一个 IP 上被发送.

这个请求中的 `version` 是什么呢.

打开视频的那一刹那, 会有这么一个请求

```text
GET /api/mobile-version?uid=58973111&view=json HTTP/1.1
Host: passport2-api.chaoxing.com
```

服务器将返回

```javascript
{
    "version": 1536923631314,
    "status": true
}
```

这个就是 `version` 的来源.

现在我们已经知道了这两种数据包的具体情况, 我们只要每隔一分钟发送一遍他们, 我们就可以让服务器认为我们真的在看网课.

而单元测验只是简单的 RestFul API, 题库可以在 Google 上搜索到, 整理之后导入数据库, 就可以实现自动答题了.

你懂我意思吧.

自动挂网课脚本的坑我会开的, 我一定会开的\(既然已经咕咕咕到现在了, 已经没有什么好害怕的了\).

