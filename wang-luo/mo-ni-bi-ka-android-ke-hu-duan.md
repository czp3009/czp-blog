# 模拟哔咔 Android 客户端

著名粉红色 APP [https://www.picacomic.com/](https://www.picacomic.com/) 大家一定非常熟悉. 今天我就来研究研究他的工作原理.

首先我们下载哔咔的 Android APP, 用支持 zip 的归档管理器打开它, 取出 dex 文件准备反编译. 当然, 也可以用 apktool\([https://github.com/iBotPeaches/Apktool](https://github.com/iBotPeaches/Apktool)\) 之类的工具来解压得到 Dalvik 字节码文件.

接下去使用 dex2jar\([https://github.com/pxb1988/dex2jar](https://github.com/pxb1988/dex2jar)\) 把 dex 文件转换为 jar 文件. 现在我们有了 JVM 字节码.

很多人喜欢直接在反编译工具, 比如 jd-gui\([https://github.com/java-decompiler/jd-gui](https://github.com/java-decompiler/jd-gui)\) 里直接查看代码, 我不推荐那么做. 通常而言, 所查看的代码很有可能已经经过混淆, 在没有代码导航和 View usage 等辅助功能的情况下, 反编译代码很难阅读. 我建议在编译工具中导出源码, 然后在 IDEA 中查看.

首先新建一个 gradle 项目, 然后把 jd-gui 导出的源码 zip 包解压缩到 `src/main/java` , 项目结构就像一个标准的 Java 项目那样

![](../.gitbook/assets/image%20%2811%29.png)

由于这是一个 Android 项目, 所以还需要使用 gradle 引入运行时\(provided\)依赖来让 IDEA 提供更多代码补全, 在 `build.gradle` 中加入

```groovy
dependencies {
    // https://mvnrepository.com/artifact/com.google.android/android
    implementation group: 'com.google.android', name: 'android', version: '4.1.1.4'
}
```

现在, IDEA 可以让我们很好的阅读反编译代码了.

对于反编译项目, 快速了解它的方法是首先查看他有哪些依赖. 很显然的, 我们可以看到哔咔使用了 `Retrofit` , 那简直是再好不过了, 随便搜索一个注解

![](../.gitbook/assets/image%20%2839%29.png)

很好, 所有 API 全部都在一个文件里面 `com.picacomic.fregata.b.a` 

然后随便导航一个方法, 比如第一个方法, 发现他在这里被调用 `com.picacomic.fregata.fragments.GameDetail` 

![com.picacomic.fregata.fragments.GameDetail.dh](../.gitbook/assets/image%20%284%29.png)

再导航到 `dM()` 

![](../.gitbook/assets/image.png)

导航至变量声明位置, 然后继续导航

![](../.gitbook/assets/image%20%2848%29.png)

![com.picacomic.fregata.b.d](../.gitbook/assets/image%20%2847%29.png)

现在, 我们甚至知道了客户端必须要有哪些请求头. 至于代码中那些不停在用 `StringBuilder` 构造的玩意, 应该是某种日志, 可见作者并不会用 `String.format` .

通过这里的代码, 我们可以很轻松的看出, 一个合法的哔咔客户端请求应当包含以下请求头

```text
//固定值
api-key: C69BAF41DA5ABD1FFEDC6D2FEA56B
accept: application/vnd.picacomic.com.v1+json
app-channel: 2
app-version: 2.2.1.3.3.4
app-uuid: defaultUuid
image-quality: original
app-platform: android
app-build-version: 44
User-Agent: okhttp/3.8.1
//动态的
time: 当前时间戳
nonce: 随机32长度字符串(0..9 + a..f)
signature: 用于验证请求的签名
authorization: 绝大部分 API 所需的 token
```

其中 `app-channel` 就是打开 APP 时选择的那个分流, 序号从 1 开始.

可能有人不太清楚 `app-uuid` 的值是怎么确定的, 只需要从这里开始一路导航进去

![](../.gitbook/assets/image%20%2846%29.png)

![com.picacomic.fregata.c.d](../.gitbook/assets/image%20%285%29.png)

而 `app-version` 和 `app-build-version` 是通过资源文件加载的

![](../.gitbook/assets/image%20%2832%29.png)

所以我们要用 apktool 导出安装包中已被编译了的 xml 文件. 很显然, 这不是一个 i18n, 所以一定在全局的字符串资源中, 我们找到这个文件

![res/values/strings.xml](../.gitbook/assets/image%20%2856%29.png)

然后直接搜索 `VERSION` 

![](../.gitbook/assets/image%20%2849%29.png)

现在, 我们知道了这几个东西的值了.

至于 `User-Agent` , 那是 `OkHttp` 自己加上去的, 我们可以在这里找到他的版本

![okhttp3.internal.Version](../.gitbook/assets/image%20%2838%29.png)

接下去我们讲讲那几个动态的请求头.

`time` 就是当前时间戳, 这没什么好讲的.

`nonce` 是一个专有名词, 在请求中表示只使用一次的, 每次都不一样的变量, 通常是一个随机数, 用于阻止请求重放. 而在哔咔中, 他的源码是这样的

```java
String str2 = UUID.randomUUID().toString().replace("-", "");
```

哔咔的作者为了生成一个 32 长度随机字符串, 居然想到先生成一个 UUID 然后替换掉里面的 `-` , 令人摸不到头脑.

`signature` 就是签名, 在一般的验证方案中, 这种签名就是把某些字符串全部拼起来, 然后做一个什么算法. 我们来看一下它的源码\(com.picacomic.fregata.MyApplication.c\)

```java
String str4 = request.url().toString().replace("https://picaapi.picacomic.com/", "");
str4 = MyApplication.bx().c(new String[] { 
    "https://picaapi.picacomic.com/", str4, str3, str2, request.method(), "C69BAF41DA5ABD1FFEDC6D2FEA56B", d.version, d.tt 
});
```

传入的字符数组中各个元素按照顺序分别是 `picaAPIBaseUrl, path, time, nonce, method, apiKey, version, buildVersion`

我们再来看看这个方法 `c` 是什么蛇神牛鬼

![com.picacomic.fregata.MyApplication.c](../.gitbook/assets/image%20%2836%29.png)

虽然有很多不知所云的代码, 但是勉强还能看懂. 首先通过将数组中的每个元素拼接起来, 分隔符为 ", "\(逗号空格\) 构造了一个 `str2` , 然后输出到日志\(RAW parameters\).

再通过调用 `getStringConFromNative(String[] paramArrayOfString)` 来得到 `str1` \(CONCAT parameters\)

最后加密时需要一个来自 `getStringSigFromNative()` 的字符串. 至于这个函数为什么被调用两遍, 为什么会被输出到日志\(CONCAT KEY\)就不得而知了.

那我们再看一看所需调用的这几个函数都是什么

```java
public native String getStringComFromNative();
  
public native String getStringConFromNative(String[] paramArrayOfString);
  
public native String getStringSigFromNative();
```

结果是 JNI, 那只能反编译了.

找到安装包带有的 so 文件, 这里取 x86\_64 平台来方便分析.

![](../.gitbook/assets/image%20%2827%29.png)

先检查一下它的 ELF header

![](../.gitbook/assets/image%20%2812%29.png)

看上去它并不需要其他第三方链接库.

然后反编译他的 `.text` 段

```bash
objdump -dj .text libJniTest.so > libJniTest.s
```

结果这几个函数写的太复杂了, 放弃了, 我们直接用 IDA 反编译为 C 再来查看它.

首先我们查看 `getStringConFromNative`

```c
__int64 __fastcall Java_com_picacomic_fregata_MyApplication_getStringConFromNative(__int64 *a1, __int64 a2, __int64 a3)
{
  __int64 v3; // rbp
  __int64 v4; // rax
  const char *v5; // r12
  const char *v6; // r13
  const char *v7; // r14
  const char *v8; // r15
  size_t v9; // rbp
  size_t v10; // r12
  size_t v11; // r13
  size_t v12; // r14
  size_t v13; // rax
  char *v14; // rbp
  char *v15; // r15
  char *v16; // r12
  char *v17; // r14
  __int64 v19; // r13
  char *v20; // [rsp+8h] [rbp-D0h]
  char *v21; // [rsp+10h] [rbp-C8h]
  char *v22; // [rsp+18h] [rbp-C0h]
  char *v23; // [rsp+20h] [rbp-B8h]
  char *s; // [rsp+28h] [rbp-B0h]
  char *src; // [rsp+30h] [rbp-A8h]
  char *v26; // [rsp+38h] [rbp-A0h]
  char *v27; // [rsp+40h] [rbp-98h]
  size_t v28; // [rsp+48h] [rbp-90h]
  size_t v29; // [rsp+50h] [rbp-88h]
  size_t v30; // [rsp+58h] [rbp-80h]
  __int64 v31; // [rsp+60h] [rbp-78h]
  __int64 v32; // [rsp+70h] [rbp-68h]
  __int64 v33; // [rsp+78h] [rbp-60h]
  __int64 v34; // [rsp+80h] [rbp-58h]
  __int64 v35; // [rsp+88h] [rbp-50h]
  __int64 v36; // [rsp+90h] [rbp-48h]
  __int64 v37; // [rsp+98h] [rbp-40h]
  __int64 v38; // [rsp+A0h] [rbp-38h]

  v3 = a3;
  v4 = *a1;
  if ( !a3 )
    return (*(__int64 (__fastcall **)(__int64 *, const char *))(v4 + 1336))(a1, "Empty parameters");
  v38 = (*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(v4 + 1384))(a1, a3, 0LL);
  s = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v38, 0LL);
  v37 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 1LL);
  src = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v37, 0LL);
  v36 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 2LL);
  v23 = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v36, 0LL);
  v35 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 3LL);
  v20 = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v35, 0LL);
  v34 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 4LL);
  v5 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v34, 0LL);
  v33 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 5LL);
  v6 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v33, 0LL);
  v32 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 6LL);
  v7 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v32, 0LL);
  v31 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 7LL);
  v8 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v31, 0LL);
  v30 = strlen(s);
  v9 = strlen(src);
  v29 = strlen(v23);
  v28 = strlen(v20);
  v22 = (char *)v5;
  v10 = strlen(v5);
  v21 = (char *)v6;
  v11 = strlen(v6);
  v27 = (char *)v7;
  v12 = strlen(v7);
  v26 = (char *)v8;
  v13 = strlen(v8);
  v14 = (char *)malloc(v13 + v12 + v11 + v10 + v28 + v29 + v30 + v9 + 2);
  if ( (unsigned int)genKey10((__int64)a1, a2) )
  {
    v15 = src;
    strcpy(v14, src);
    v16 = v23;
    strcat(v14, v23);
    strcat(v14, v20);
    strcat(v14, v22);
    strcat(v14, v21);
    v17 = s;
  }
  else
  {
    strcpy(v14, v21);
    strcat(v14, v8);
    v15 = src;
    strcat(v14, src);
    v17 = s;
    strcat(v14, s);
    v16 = v23;
    strcat(v14, v23);
    strcat(v14, v22);
    strcat(v14, v27);
    strcat(v14, v20);
  }
  v19 = (*(__int64 (__fastcall **)(__int64 *, char *))(*a1 + 1336))(a1, v14);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v38, v17);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v37, v15);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v36, v16);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v35, v20);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v34, v22);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v33, v21);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v32, v27);
  (*(void (__fastcall **)(__int64 *, __int64, char *))(*a1 + 1360))(a1, v31, v26);
  free(v14);
  return v19;
}
```

前面一大段变量声明就不用看了.

对于 native 代码而言, 如果需要调用某个 Java 方法, 就需要使用一个 JVM 提供的函数指针, 而且采取硬编码指针偏移量的方式来使用具体的某个方法, 这就导致 JNI 代码非常难以阅读. 通常来说, 包含 JVM 提供的代理到 JVM 方法的代码块会以第一个参数的方式传入.

仔细观察当前函数的定义

```c
__int64 __fastcall Java_com_picacomic_fregata_MyApplication_getStringConFromNative(__int64 *a1, __int64 a2, __int64 a3)
```

很显然, 第一个参数 `__int64 *a1` 一定是一个函数指针. 但是我们并不知道各种实际方法在从 `a1` 开始的多少偏移量之后.

这个函数应当返回 `String` 类型, 但是实际上它返回 `int64` , 所以它应当会先把 `char*` 类型的数据先存放到指定位置然后返回他的指针. 我们来看看 return 时做了什么.

```c
v4 = *a1;
return (*(__int64 (__fastcall **)(__int64 *, const char *))(v4 + 1336))(a1, "Empty parameters");
```

虽然不是很明白为什么要拷贝一次 `a1` , 不过从这一段我们可以看出, `*a1 + 1336` 这个地址对应的函数用于存放 `char*` 类型的数据并返回指针的值.

再往下可以看到形式非常规则的一段

```c
v38 = (*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(v4 + 1384))(a1, a3, 0LL);
s = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v38, 0LL);
v37 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 1LL);
src = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v37, 0LL);
v36 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 2LL);
v23 = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v36, 0LL);
v35 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 3LL);
v20 = (char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v35, 0LL);
v34 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 4LL);
v5 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v34, 0LL);
v33 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 5LL);
v6 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v33, 0LL);
v32 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 6LL);
v7 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v32, 0LL);
v31 = (*(__int64 (__fastcall **)(__int64 *, __int64, __int64))(*a1 + 1384))(a1, v3, 7LL);
v8 = (const char *)(*(__int64 (__fastcall **)(__int64 *, __int64, _QWORD))(*a1 + 1352))(a1, v31, 0LL);
```

这里反复在做两件事, 第一件事是得到一个指针, 第二件事是通过这个指针得到 `char*` . 众所周知, Java 内置的 `String` 类型使用 UTF16 编码, 而 C 没有原生的 Unicode 处理, `wchar_t` 那是 cpp 的东西. 所以这其中必须首先经过某些转换才能得到在 C 中可用的 `char*` . 并且由于 JVM 中的数组类型是包含长度的, 这些信息在本地语言中都不存在, 所以数组类型的传入值一定不是直接获取其中的元素的. 因此不难猜到, `*a1 + 1384` 用于取得在 Java 中的传入值 `paramArrayOfString` 中的每一个元素, 数组偏移量就是此函数的第三个参数\(`_QWORD` 即 quad word, 长度为八字节\), 通过分别使用 0 到 7 取得八个传入值的内存地址\(哪八个见上文\). 那么对应的, `*a1 + 1352` 用于将 UTF16 转为 `char*` .

接下去, 分别测量了这八个参数的长度, 然后构造了这样一个内存块.

```c
v14 = (char *)malloc(v13 + v12 + v11 + v10 + v28 + v29 + v30 + v9 + 2);
```

这很明显是要进行字符串拼接了, 至于为什么长度最后加二而不是加一不得而知.

随后开始了拼接\(部分变量已手动取名\)

```c
if ( (unsigned int)genKey10(a1, a2) )
{
  _arg1 = arg1;
  strcpy(buffer, arg1);
  _arg2 = arg2;
  strcat(buffer, arg2);
  strcat(buffer, arg3);
  strcat(buffer, _arg4);
  strcat(buffer, _arg5);
  _arg0 = arg0;
}
else
{
  strcpy(buffer, _arg5);
  strcat(buffer, arg7);
  _arg1 = arg1;
  strcat(buffer, arg1);
  _arg0 = arg0;
  strcat(buffer, arg0);
  _arg2 = arg2;
  strcat(buffer, arg2);
  strcat(buffer, _arg4);
  strcat(buffer, _arg6);
  strcat(buffer, arg3);
}
```

首先调用了 `genKey10` 这个函数, `*a1` 就是之前用了好几次的 JVM 调用的函数指针起始位置, `*a2` 就是 Java 中的传入值, 类型是 `String[]` .

至于这个函数是做什么的, 看了半天愣是没看懂. 此前看过一篇文章 [https://blog.kaaass.net/archives/1074](https://blog.kaaass.net/archives/1074) 似乎是用来校验 APK 签名的. 但是为什么校验失败之后会有另一种拼接方式, 令人困惑. \(此后我尝试了模拟校验失败之后的情况, 用 else 分支中的逻辑来拼接并产生 signature, 但是服务器的返回总是没有 data 字段, 这是 signature 错误的表现. 所以这可能仅仅是为了阻止第三方修改 APK\)

那么在正常情况下, `str1` 最终的值就是拼接参数 1 到 5, 类似这样

```kotlin
val str1 = "$path$time$nonce$method$apiKey"
```

然后我们再去看 `getStringSigFromNative` 函数是什么

```c
__int64 __fastcall Java_com_picacomic_fregata_MyApplication_getStringSigFromNative(__int64 a1, __int64 a2)
{
  const char *v2; // rsi
  __int64 result; // rax
  _WORD v4[36]; // [rsp+0h] [rbp-58h]
  unsigned __int64 v5; // [rsp+48h] [rbp-10h]

  v5 = __readfsqword(0x28u);
  strcpy((char *)v4, "~*}$#,$-\").=$)\",,#/-.'%(;$[,|@/&(#\"~%*!-?*\"-:*!!7pddUBL5n|0/*Cn");
  HIBYTE(v4[0]) = 100;
  *(_DWORD *)((char *)&v4[3] + 1) = 1768835429;
  v4[8] = 19282;
  *(_WORD *)((char *)&v4[16] + 1) = 25213;
  v4[2] = 14161;
  LOBYTE(v4[6]) = 86;
  HIBYTE(v4[9]) = 80;
  *(_WORD *)((char *)&v4[10] + 1) = 19794;
  HIBYTE(v4[15]) = 67;
  LOBYTE(v4[16]) = 65;
  v4[18] = 22351;
  *(_WORD *)((char *)&v4[20] + 1) = 22085;
  HIBYTE(v4[21]) = 96;
  HIBYTE(v4[22]) = 60;
  v4[23] = 19774;
  v4[7] = 23609;
  HIBYTE(v4[11]) = 52;
  HIBYTE(v4[12]) = 57;
  HIBYTE(v4[13]) = 55;
  HIBYTE(v4[19]) = 51;
  if ( (unsigned int)genKey10(a1, a2) )
    v2 = (const char *)v4;
  else
    v2 = "vgh$;!~y8fjlsdvaAGDRWbcljg9atb/30P@f:v.Byehuofdo|fjwh35bfuD=dkr";
  result = (*(__int64 (__fastcall **)(__int64, const char *))(*(_QWORD *)a1 + 1336LL))(a1, v2);
  if ( __readfsqword(0x28u) != v5 )
    JUMPOUT(0x2154LL);
  return result;
}
```

这玩意首先定义了一个字符串, 然后再在它的各个下标用其他值替换原有的字符, 甚至有些替换是两个字节一起换的. 脑内模拟这一过程太过困难, 我们直接来运行一下它得到输出.

![](../.gitbook/assets/image%20%2859%29.png)

现在我们得到了用于产生 signature 所用的密钥\(这对应于 2.2.1.3.3.4 版本\)

```text
~d}$Q7$eIni=V)9\RK/P.RM4;9[7|@/CA}b~OW!3?EV`:<>M7pddUBL5n|0/*Cn
```

接下去我们回到 Java 中, 看看如何使用之前得到的 `RAW parameters` 和 `CONCAT KEY` 

```java
return this.hl.C(str1, getStringSigFromNative());
```

结果这个 `C` 方法反编译不出来

![com.picacomic.fregata.utils](../.gitbook/assets/image%20%2845%29.png)

那我们试试直接用 IDEA\(Fernflower\) 来反编译, 这下反编译出来了, 原来是这段代码用了太多 label

```java
//
// Source code recreated from a .class file by IntelliJ IDEA
// (powered by Fernflower decompiler)
//

package com.picacomic.fregata.utils;

import java.io.UnsupportedEncodingException;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

public class d {
    public static final String TAG = "d";
    protected static final char[] uq = "0123456789abcdef".toCharArray();
    String uo;

    public d() {
    }

    public static String a(byte[] var0) {
        char[] var4 = new char[var0.length * 2];

        for(int var1 = 0; var1 < var0.length; ++var1) {
            int var2 = var0[var1] & 255;
            int var3 = var1 * 2;
            var4[var3] = uq[var2 >>> 4];
            var4[var3 + 1] = uq[var2 & 15];
        }

        return new String(var4);
    }

    public String C(String var1, String var2) {
        synchronized(this){}

        Throwable var10000;
        label130: {
            boolean var10001;
            byte[] var23;
            label123: {
                UnsupportedEncodingException var22;
                try {
                    try {
                        var23 = var2.getBytes("UTF-8");
                        break label123;
                    } catch (UnsupportedEncodingException var19) {
                        var22 = var19;
                    }
                } catch (Throwable var20) {
                    var10000 = var20;
                    var10001 = false;
                    break label130;
                }

                try {
                    var22.printStackTrace();
                    var23 = new byte[0];
                } catch (Throwable var18) {
                    var10000 = var18;
                    var10001 = false;
                    break label130;
                }
            }

            label115:
            try {
                var1 = var1.toLowerCase();
                String var3 = TAG;
                StringBuilder var4 = new StringBuilder();
                var4.append("RAW SIGNATURE = ");
                var4.append(var1);
                f.D(var3, var4.toString());
                this.uo = this.a(var1, var23);
                var1 = this.uo;
                return var1;
            } catch (Throwable var17) {
                var10000 = var17;
                var10001 = false;
                break label115;
            }
        }

        Throwable var21 = var10000;
        throw var21;
    }

    protected String a(String var1, byte[] var2) {
        try {
            Mac var3 = Mac.getInstance("HmacSHA256");
            var3.init(new SecretKeySpec(var2, "HmacSHA256"));
            var1 = a(var3.doFinal(var1.getBytes("UTF-8")));
            return var1;
        } catch (Exception var4) {
            var4.printStackTrace();
            return null;
        }
    }
}

```

结果整了那么大一圈, `C` 方法的作用只是把之前得到的 `str1` 转为小写然后用 `a` 方法进行 SHA256 加密.

那现在我们知道 signature 是怎么生成的了

```kotlin
val apiKey = "C69BAF41DA5ABD1FFEDC6D2FEA56B"
val apiSecret = "~d}$Q7$eIni=V)9\\RK/P.RM4;9[7|@/CA}b~OW!3?EV`:<>M7pddUBL5n|0/*Cn"

val time = Instant.now().epochSecond
val nonce = UUID.randomUUID().toString().replace("-", "")
val path = url.buildString().substringAfter("https://picaapi.picacomic.com/")
val raw = "$path$time$nonce$method$apiKey".toLowerCase()
val signature = hmacSHA256(raw, apiSecret).convertToString()
```

有了所有 header 的生成方法, 现在我们需要知道哔咔是怎么登录的. 我们回到之前的那个描述 API 用的 interface, 找到如下一段

```java
@POST("auth/sign-in")
Call<GeneralResponse<SignInResponse>> a(@Body SignInBody paramSignInBody);
```

其中所用的 `SignInBody` 是这样的

```java
public class SignInBody {
  @SerializedName("email")
  String email;
  
  @SerializedName("password")
  String password;
}
```

根据这些来构造请求

```text
REQUEST: https://picaapi.picacomic.com/auth/sign-in
METHOD: HttpMethod(value=POST)
COMMON HEADERS
-> api-key: C69BAF41DA5ABD1FFEDC6D2FEA56B
-> app-channel: 2
-> time: 1577428812
-> nonce: bca3ad865d290f461c9dc0708c45593d
-> signature: d9e860f60af8f069fae96118d465d913cd1e5bad8c107e3ef41e2db4f099a5e8
-> app-version: 2.2.1.3.3.4
-> app-uuid: defaultUuid
-> image-quality: original
-> app-platform: android
-> app-build-version: 44
-> User-Agent: okhttp/3.8.1
-> accept: application/vnd.picacomic.com.v1+json
CONTENT HEADERS
BODY Content-Type: application/json; charset=UTF-8
BODY START
{"email":"onlyForTest","password":"onlyForTest"}
BODY END
```

请求成功之后, 服务器将返回

```javascript
{
  "code": 200,
  "message": "success",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiI1ZGM0MWFkOWE0OTBlYTA2NGNmZDJhZDciLCJlbWFpbCI6Im9ubHlmb3J0ZXN0Iiwicm9sZSI6Im1lbWJlciIsIm5hbWUiOiJvbmx5Rm9yVGVzdCIsInZlcnNpb24iOiIyLjIuMS4zLjMuNCIsImJ1aWxkVmVyc2lvbiI6IjQ0IiwicGxhdGZvcm0iOiJhbmRyb2lkIiwiaWF0IjoxNTc3NDI4ODEzLCJleHAiOjE1NzgwMzM2MTN9.AbC7TdY1lqCbjy6xLPds9uoMJtcITSLJonXIFA0-G4U"
  }
}
```

\(如果用户名或密码不正确返回的 message 将不是 success. 如果 signature 错误, 将返回 success 但是没有 data 字段\)

返回内容中的 `token` 就是访问其他 API 时 header 中需要的 `authorization` 的值.

而至于为什么很多 API 写成这样

```java
@GET("categories")
Call<GeneralResponse<CategoryResponse>> al(@Header("authorization") String paramString);
```

很显然哔咔的作者无法理解什么是有状态的拦截器.

更离奇的是简单的分页能被写出这样的模型

![](../.gitbook/assets/image%20%2810%29.png)

哔咔 APP 的编译版本是 Java6.0, 很难想象由于不知道泛型而把这种类写了几十遍是如何的勤奋\(如同网易内部的非透明网关所用的包装类返回 Object 类型一样精彩\).

剩下的 API 没有什么特别的, 全部都在 `com.picacomic.fregata.b.a` , 感兴趣的可以自行查看. 如果懒得反编译的话可以看这则 gist [https://gist.github.com/czp3009/ce9de65b9784108d6bf419614f1dd89f](https://gist.github.com/czp3009/ce9de65b9784108d6bf419614f1dd89f)

早些时间实现了一个哔咔 API 的调用库 [https://github.com/czp3009/picacomic-api/](https://github.com/czp3009/picacomic-api/)

这应该是少有的填完的坑, 小伙伴们痛哭流涕.

