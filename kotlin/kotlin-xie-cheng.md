# Kotlin 协程

如果你还不知道异步是什么, 请看上一篇文章 [异步是什么](../wang-luo/yi-bu-shi-shen-me.md).

协程所解决的, 并不是为程序提供计算密集型问题的解决方案(线程管理), 而是提供对因为访问了不会立即返回的资源(例如网络, 文件等)的异步操作的包装, 使得代码更容易编写和阅读. 简单地说, **这是另一种并发代码编写方式**. 协程在很多语言都存在而且写法不一定相同, 本文主要介绍 Kotlin 中的协程.

先来看一段最简单的协程代码

```kotlin
fun main() {
    GlobalScope.launch {
        var variable = 1
        println("variable is $variable")
        delay(1000)
        variable++
        println("variable is $variable")
        delay(1000)
        println("variable is $variable")
    }
    Thread.sleep(3000)  //阻塞主线程保证程序不结束
}
```

`GlobalScope.launch` 用于启动一个协程. `delay` 是一个挂起(suspend)函数, 它不会阻塞线程, 但是会挂起协程, 用于在协程中实现延迟. 可能看到这里还是无法理解协程是什么, 没关系, 先来回想一下正常异步代码是怎么写的.

在很多时候, 对异步函数的调用都需要传入一个回调函数, 如果运行时有内建的事件循环可能还可以写成 `Promise.then` 的形式. 但是无论如何这样都无法避免当代码逐渐复杂之后产生的回调套回调问题, 也就是回调地狱.

在函数式编程中, 通过回调来处理返回结果的模式叫做延续传递风格(continuation-passing style), 简称 CPS. 即使是表面写成 then 的形式也是 CPS.

![CPS](<../.gitbook/assets/image (70).png>)

就像人类易读的中缀表达式可以通过固定算法转换为计算机所需的前缀表达式一样, 编译器也可以做到把人类易读的顺序代码转换为 CPS. 这种转换叫做 CPS 变换(CPS transformation), 经过了变换的协程代码可能会变成这样(伪代码):

```kotlin
fun main() {
    var variable = 1
    println("variable is $variable")
    delay(1000, callback = {
        variable++
        println("variable is $variable")
        delay(1000, callback = {
            println("variable is $variable")
        })
    })
    Thread.sleep(2000)  //阻塞主线程保证程序不结束
}
```

通过编译器实现的 CPS 变换, 编译器将没有回调的源码变成了满是回调的代码, 从而实现异步调用.

协程自身是一个抽象化概念, 有时候它会被描述为轻量级线程(light-weight thread), 虽然协程跟线程没什么关系, 但是在高层代码上非常相似, 正是这种相似使得用户可以以类似同步代码的方式编写出异步代码. **协程并不创造异步, 而是将原本难写的异步代码包装成看似同步的方式**.

然而事情到此仍然没有结束. 仅仅通过 CPS 变换得到的异步代码, 回调之间有调用栈, 也就是最外层回调调用内层回调再调用内层回调. 变换结果存在调用栈, 所以这种协程也叫有栈协程. 在源码中, 将被变换为非常内层的回调的代码(闭包)也可能使用跨了好几层回调的外面的变量, 用协程的概念来说就是需要能保存上下文.

众所周知函数内的局部变量保存在栈内存中, 如果一段协程代码被变换为层层叠叠几百层回调, 也就意味着这跟一个很深递归函数类似, 将存在爆栈风险. 很多有协程功能的语言(比如 go 语言)可以对这一点进行优化, 使协程不会爆栈, 但是内存依然要被消耗很多(相对于无栈协程), 而且开启协程的开销也会更大(以至于 go 社区甚至出现了协程复用池这种奇葩的东西). 但是有栈协程有一个不可比拟的好处, 那就是运行时可以在函数的任意位置跳出(挂起), 这是无栈协程做不到的.

而 kotlin 的协程是无栈协程. 在 CPS 的基础上, 编译器给每个协程都增加了状态机.

`GlobalScope.launch` 在使用时, 后面紧接一个大括号, 这本身是传入一个函数作为回调, 在最终编译得到的字节码中会有这样的一行(反编译为 java):

```kotlin
BuildersKt.launch$default(GlobalScope.INSTANCE, null, null, new ApplicationKt$main.ApplicationKt$main$1(null), 3, null);
```

这一行在 `GlobalScope` (它是一种 `CoroutineScope`)内部包含的 `CoroutineContext` 中的 dispatcher(协程调度器) 中启动了一个新的协程. 而源码中的大括号内的部分被编译为了一个类 `ApplicationKt$main$1` , 这个类大约是这样的:

```kotlin
static class ApplicationKt$main$1 extends SuspendLambda implements Function2<CoroutineScope, Continuation<? super Unit>, Object> {
    int I$0;    //variable 被储存为成员变量
    int label;

    public Object invokeSuspend(Object $result) {
        //COROUTINE_SUSPENDED 是一个 enum
        Object coroutine_SUSPENDED = IntrinsicsKt.getCOROUTINE_SUSPENDED();
        int variable = 0;
        Label_0167: {
            switch (this.label) {
                case 0: {
                    //step 0
                    ResultKt.throwOnFailure($result);
                    variable = 1;
                    System.out.println(Intrinsics.stringPlus("variable is ", Boxing.boxInt(variable)));
                    long n = 1000L;
                    Continuation continuation = this;
                    this.I$0 = variable;
                    this.label = 1;
                    if (DelayKt.delay(n, continuation) == coroutine_SUSPENDED) {
                        return coroutine_SUSPENDED;
                    }
                    break;    //如果第一个 delay 设定的时间为 0 将直接跳出 switch 开始运行 step 1
                }
                case 1: {
                    variable = this.I$0;
                    ResultKt.throwOnFailure($result);
                    break;
                }
                case 2: {
                    variable = this.I$0;
                    ResultKt.throwOnFailure($result);
                    break Label_0167;
                }
                default: {
                    throw new IllegalStateException("call to 'resume' before 'invoke' with coroutine");
                }
            }
            //step 1
            ++variable;
            System.out.println(Intrinsics.stringPlus("variable is ", Boxing.boxInt(variable)));
            long n2 = 1000L;
            Continuation continuation2 = this;
            this.I$0 = variable;
            this.label = 2;
            if (DelayKt.delay(n2, continuation2) == coroutine_SUSPENDED) {
                return coroutine_SUSPENDED;
            }
            //如果因为第二个 delay 设置的时间为 0 而到达 if 后方将直接开始执行 step 2
        }
        //step 2
        System.out.println(Intrinsics.stringPlus("variable is ", Boxing.boxInt(variable)));
        return Unit.INSTANCE;    //最终的返回类型为 Unit
    }

    public Continuation<Unit> create(Object value, Continuation<?> $completion) {
        return new ApplicationKt$main$1($completion);
    }

    public Object invoke(CoroutineScope p1, Continuation<? super Unit> p2) {
        return this.create(p1, p2).invokeSuspend(Unit.INSTANCE);
    }
}
```

由于源码中的 `delay` 函数是一个 suspend 函数, 编译器将以此为切分点构建状态机, 使用 label 来标识当前运行到的地方, 以待之后切换回此协程时可以恢复状态. 而所有变量将被编译为类的成员变量, 存储在堆内存, 因此不会因为建立了很多协程而爆栈.

源码被切分为三个部分(对应到 switch 选择分支的 0, 1, 2):

```kotlin
//step 0
var variable = 1
println("variable is $variable")
delay(1000)
//step 1
variable++
println("variable is $variable")
delay(1000)
//step 2
println("variable is $variable")
```

状态机代码会利用 break 和 label(指语法级 label) 来选择跳转到的部分, 每当一个部分已经执行过, label(协程内部的变量) 将被设置为之前的值加一, 下一次切换回此协程时将进入下一部分.

当协程调度器运行一个协程后, 协程会返回一个返回值给调度器, 如果协程返回 `COROUTINE_SUSPENDED` 这一 enum, 协程调度器就会知道此协程尚未运行完毕, 并保留协程在队列中, 开始执行下一个协程. `DelayKt.delay(n, continuation)` 会为协程设定延迟(continuation 变量指向协程本身), 如果协程调度器已经将循环队列运行了一圈回到了原来的这个协程, 发现当前时间仍未达到此协程的最早可运行时间, 就会再次无视此协程开始执行下一个协程. 如果整个队列中的全部协程当前都不可被执行, 协程调度器会 sleep 自己的线程到最近的可执行时间为止. 如果协程返回除 `COROUTINE_SUSPENDED` 之外的值, 协程调度器就会认为此协程已经完全执行完毕并从协程队列中移除此协程.

无栈协程与一些脚本语言的 generator 模式有点类型, 实际上语法级别的 `yield` 也是通过 label 实现的. 无栈协程的一个缺陷是不能在任意位置跳出, 回到调度器代码, 而必须由协程主动运行到某个 suspend 函数才有机会跳出. 这意味着如果一个协程中执行了一个阻塞的操作(包括死循环), 将卡住协程调度器. 为了改善这一问题, kotlin 的协程调度器是可自定义的, 上面提到的 `GlobalScope` 所用的调度器是多线程的, 同一时间会有多个协程在运行, 即使卡住一些协程也不会完全卡住整个基于协程的程序.

基于无栈协程的性质, kotlin 协程中应尽可能只执行非耗时任务, 而不能立即返回的资源必须包装为 suspend fun. 在最终连接阻塞世界和协程世界的地方(某个函数), 只要使其返回 `COROUTINE_SUSPENDED` 就可以让协程调度器跳出去做别的事情, 协程可以在协程调度器之外的地方(其他线程)被通过 `resume` 方法调用来结束协程. 举个例子(kotlin.coroutines.intrinsics 包下的内容需要手动导入, IDEA 不会自动补全):

```kotlin
import kotlinx.coroutines.async
import kotlinx.coroutines.runBlocking
import kotlin.concurrent.thread
import kotlin.coroutines.intrinsics.COROUTINE_SUSPENDED
import kotlin.coroutines.intrinsics.suspendCoroutineUninterceptedOrReturn
import kotlin.coroutines.resume
import kotlin.system.measureTimeMillis

suspend fun sendRequest() = suspendCoroutineUninterceptedOrReturn<Unit> { continuation ->
    thread {
        Thread.sleep(1000)
        continuation.resume(Unit)
    }
    COROUTINE_SUSPENDED
}

fun main() {
    runBlocking {
        val time = measureTimeMillis {
            val request1 = async { sendRequest() }
            val request2 = async { sendRequest() }
            request1.await()
            request2.await()
        }
        println("two request completed in $time ms")    //1000 ms
    }
}
```

其中 `suspendCoroutineUninterceptedOrReturn` 是一个编译器魔法, 仅仅用来为用户提供当前协程的 continuation. `sendRequest` 方法经过 CPS 变换会变成类似这样子:

```kotlin
fun sendRequest(continuation : Continuation<Unit>): Any {
    thread {
        Thread.sleep(1000)
        continuation.resume(Unit)
    }
    COROUTINE_SUSPENDED
}
```

在 jvm 字节码中, 每个 suspend fun 的最后一个参数都是 continuation, 返回类型都是 Object(对应 kotlin 中的 Any). 返回类型为 Object 正是因为返回值可能是 `COROUTINE_SUSPENDED`.

阻塞世界可以在任务开始时创建一个协程, 任务结束时结束对应协程从而连接到协程世界. 对于本来使用回调的异步代码同样可以如法炮制, 当回调被运行时结束协程来包装到协程. 协程模型可以描述所有异步过程, 最终在高层提供逻辑统一的异步模型. 很多的已有的库已经有包装到 kotlin coroutine 的扩展, 比如 ReactiveX, Netty, Retrofit 等. 回调包装成协程的例子:

```kotlin
val channelGroup = AsynchronousChannelGroup.withFixedThreadPool(1) { Thread(it) }

class AsyncSocket {
    val socketChannel = AsynchronousSocketChannel.open(channelGroup)

    suspend fun connect(host: String, port: Int) {
        suspendCoroutine<Void> { continuation ->
            val address = InetSocketAddress.createUnresolved(host, port)
            socketChannel.connect(address, Unit, CoroutineCompletionHandler(continuation))
        }
    }

    suspend fun read(byteBuffer: ByteBuffer) = suspendCoroutine<Int> { continuation ->
        socketChannel.read(byteBuffer, Unit, CoroutineCompletionHandler(continuation))
    }

    suspend fun send(byteBuffer: ByteBuffer) = suspendCoroutine<Int> { continuation ->
        socketChannel.write(byteBuffer, Unit, CoroutineCompletionHandler(continuation))
    }

    fun close() = socketChannel.close()

    private class CoroutineCompletionHandler<T>(val continuation: Continuation<T>) : CompletionHandler<T, Unit> {
        override fun completed(result: T, attachment: Unit) {
            continuation.resume(result)
        }

        override fun failed(exc: Throwable, attachment: Unit) {
            continuation.resumeWithException(exc)
        }
    }
}

suspend fun asyncGet(url: String): Json {
    val asyncSocket = AsyncSocket().apply {
        connect(url, 443)
    }
    val byteBuffer = ByteBuffer.allocateDirect(Int.MAX_VALUE)
    //TODO: construct http request here
    byteBuffer.put(httpRequestBytes)   //write http request to byteBuffer
    asyncSocket.send(byteBuffer)
    byteBuffer.rewind() //reuse buffer
    asyncSocket.read(byteBuffer)
    val byteArray = byteBuffer.moveToByteArray()    //parse http response
    //TODO: parse http response here
    asyncSocket.close()
    return json //parse result
}

suspend fun main() {
    repeat(5) {
        val result1 = asyncGet("https://api.github.com/users/czp3009/repos")
        val firstRepoName = result.asArray.first().name
        val result2 = asyncGet("https://api.github.com/repos/czp3009/$firstRepoName")
        println($result2.asObject["full_name"])
    }
}
```

AsynchronousSocketChannel 是 java 标准库提供的操作系统网络 IO 事件通知机制(在 Linux 上为 epoll, Mac 上为 kqueue, 在 Windows 上为 IOCP). 它会在提供给它线程池中创建一个长期存在的线程去阻塞地获取对多个 socket 的事件通知, 只要指定的 socket 中任意一个有事件可用, 他就会处理此事件(已连接, 有数据, 已断开等)并调用回调. 传递给它的线程池中的其他线程会被用来执行回调(如果线程池不为 Fixed 类型), 我们不需要在回调中执行耗时任务, 因此只给他一个线程, 它将在一个线程上完成对多个 socket 的监听并调用给予的回调. 在回调中结束之前的协程, 继而将回调世界连接到协程世界. 在高层就可以写出非常漂亮的无回调异步代码. 这很类似于 Node.js 中通过在回调结束时执行 Promise 内部的 resolve 从而将回调式调用转换为 Promise 调用.

kotlin 协程在从阻塞世界(至少 main 函数在原理上是阻塞的)(main 函数也可以是 suspend fun, 不过那是语法糖)进入时需要显式指定 CoroutineScope, 里面的协程上下文会决定协程调度器, 所有的这一切都是可自定义的. 上例子中的 `runBlocking` 是协程标准库自带的函数, 他会用阻塞当前线程并用此线程作为调度器, 直到所有协程结束. 这种高配置性也为 kotlin 协程提供了无限可能.

kotlin 协程的集大成者自然是 jetbrains 发布的 [ktor](https://ktor.io) , 它是一个基于协程的全异步 http web server 框架, CIO 引擎更是直接从 Epoll 开始包装为协程, 洁癖狂喜. 如果你还没有试过使用协程来编写代码, 你应该赶紧试试 ktor. Keep moving, don't blocking!
