# 异步是什么

在了解异步之前, 先来理解一下什么是同步. 假设有以下伪代码

```javascript
const result = get("https://api.github.com/users/czp3009/repos")
console.log(result.length)
```

`get` 函数会访问 github api 并以 json 格式返回 api 的 ResponseBody. 在 `get` 函数返回前, 后面的代码不会被执行. 而如果网络耗时较长, 我们就会感受到 `get` 函数会"卡住"程序的运行. 这种"卡住"就是所谓的"阻塞".

简单地说, 如果一个函数被调用后, **在结果返回前, 线程会一直阻塞**, 那么这个函数就是同步的.

阻塞并不是类似某些单片机代码那样用 while(true) 来让 cpu 空转从而不进入下一行, 阻塞是操作系统实现的. 现代操作系统都是分时操作系统, 既然操作系统可以为每个线程分配时间片, 自然也就可以停止对某个线程的时间片分配, 让线程"挂起".

操作系统的基础工作之一是管理计算机的资源, 应用程序不能直接访问计算机上的资源, 包括文件, 网络等等. 因此所有资源的访问最底层都是由操作系统提供的接口(system call). 比如说从 socket 中读取字节时所用的 read 函数就是操作系统提供的(c 语言).

```c
#include <unistd.h>

ssize_t read(int fs, void *buf, size_t N);
```

当应用程序调用了 read 函数, 而目前 socket 中无字节可读时, 操作系统就会挂起此线程, 不再为此线程分配时间片, 直到 socket 中有新的数据到来. 除此之外, 其他的会"卡住"线程的功能, 比如锁最终也是由操作系统实现的, 如果未能获得锁此线程就会被操作系统挂起直到锁可用.

阻塞是很多程序中不愿意出现的, 比如说现在有一个 UI 程序, 当按钮按下时会发送一个 HTTP 请求. 如果按钮的代码是同步的, 那么按钮按下去之后就会保持按下的状态不会立即弹回来, 因为线程被阻塞了. 最简单的解决这个问题的办法就是创建一个新的线程, 让这个新线程代替主线程去"卡住". 这样主线程的按钮代码是立即执行完毕的, 与之前的同步代码相比, **调用函数后可以立即进行其他操作就是异步**(对主线程而言).

多线程是一种常用的异步实现, 但是在大多数情况下, 异步并不是因为操作是计算密集型的, 而是因为操作调用了某种返回速度很慢的计算机资源, 比如上面作为例子的网络 IO. 其本质是相对高速的 CPU 与响应时间很长的网络之前的不匹配, 因此 CPU 必须有一段时间的 suspend 来等待网络 IO 继而进行下一步操作.

为了能够利用网络请求的返回内容, 还需要将本来写在 `get` 函数下方的代码以回调方式传入新的异步函数 `asyncGet`

```javascript
function asyncGet(url, callback) {
    new Thread(()=>{
        const result = get(url)
        callback(result)
    }).start()
}

asyncGet("https://api.github.com/users/czp3009/repos", result=>{
    console.log(result.length)
})
//next line
```

因此异步的一个表观特征是代码的执行顺序和源码顺序不相同. `asyncGet` 函数会立即返回让程序进入下一行, 而回调则在网络请求结束后才被执行.

采用多线程的方式, 我们可以为主线程上每一处进行网络 IO 的地方都创建一个新线程来发送请求, 从而保持主线程永远不阻塞. 但是很快的, 我们会发现线程是一种比较贵的资源. 线程一经创建, 操作系统会为它分配栈空间, 创建出几千个线程就会少掉一大块内存. 另外, 线程数量很多之后, 操作系统进行线程上下文切换的开销甚至会超过任务本身的开销. 因此通过多线程来实现异步网络 IO 并不是非常好的办法.

阻塞出在操作系统上, 既然如此, 那么能不能与操作系统商量一下, 让应用程序能在一个线程中创建一千个 socket 后被操作系统挂起, 然后要求操作系统只要这一千个 socket 中有任意一个 socket 有字节可用就唤醒此线程, 从而避免创建一千个线程去分别等待它们呢. 这种办法是有的, 在现代操作系统上都有这种 IO 事件通知机制. 在 Linux 中称为 epoll, 在 BSD 系列中称为 kqueue, NT 上的等价品叫做 iocp. 以 epoll 为例, 最终会有以下 c 语言代码

```c
#define MAX_EVENTS 10
struct epoll_event ev, events[MAX_EVENTS];
int listen_sock, conn_sock, nfds, epollfd;

/* Code to set up listening socket, 'listen_sock',
  (socket(), bind(), listen()) omitted. */

epollfd = epoll_create1(0);
if (epollfd == -1) {
   perror("epoll_create1");
   exit(EXIT_FAILURE);
}

ev.events = EPOLLIN;
ev.data.fd = listen_sock;
if (epoll_ctl(epollfd, EPOLL_CTL_ADD, listen_sock, &ev) == -1) {
   perror("epoll_ctl: listen_sock");
   exit(EXIT_FAILURE);
}

for (;;) {
   nfds = epoll_wait(epollfd, events, MAX_EVENTS, -1);
   if (nfds == -1) {
       perror("epoll_wait");
       exit(EXIT_FAILURE);
   }

   for (n = 0; n < nfds; ++n) {
       if (events[n].data.fd == listen_sock) {
           conn_sock = accept(listen_sock, (struct sockaddr *) &addr, &addrlen);
           if (conn_sock == -1) {
               perror("accept");
               exit(EXIT_FAILURE);
           }
           setnonblocking(conn_sock);
           ev.events = EPOLLIN | EPOLLET;
           ev.data.fd = conn_sock;
           if (epoll_ctl(epollfd, EPOLL_CTL_ADD, conn_sock, &ev) == -1) {
               perror("epoll_ctl: conn_sock");
               exit(EXIT_FAILURE);
           }
       } else {
           do_use_fd(events[n].data.fd);
       }
   }
}
```

这种事件驱动(event-driven)的网络 IO 模式可以让应用程序告诉操作系统想要监听哪一些 socket, 然后应用程序用一个线程去循环取得对应的事件, 包括连接成功, 连接断开, 有字节可用等. 如果当前没有事件可用, 此线程将在 `epoll_wait` 函数被阻塞. 而一旦有事件, 此线程就会被唤醒, 程序可以从事件中取出事件类型以及对应的 socket, 继而对其进行处理. 这样应用程序就只需要一个线程就可以管理大量 socket. 除非完全没有事情可做(没有事件), 否则此线程就永远在工作(处理事件).

因此**对于非计算密集型任务, 使用多线程来实现异步是不必要的**. 现代操作系统均已对网络, 文件等阻塞的源头提供了相应的事件驱动 API. 事件驱动是如此的好用以至于很多语言对程序内部的一些过程, 比如延迟函数, 计时器等也使用事件驱动的方式来实现. Node.js 所用的 libuv 就是一个很好地例子, 这使得整个运行时就是一个事件队列.

然而, 虽然通过事件驱动的方式解决了线程过多的问题, 在对底层的 socket 访问代码进行包装后, 高层代码依然使用回调的模式来利用请求的返回内容. 如果第二个请求依赖于第一个请求的返回内容, 那么第二个请求就必须写到第一个请求的回调内部去, 最终回调里套了别的回调(下例伪代码中的 `asyncGet` 函数包装了 epoll 而不是多线程)

```javascript
asyncGet("https://api.github.com/users/czp3009/repos", result=>{
    const firstRepoName = result[0].name
    asyncGet(`https://api.github.com/repos/czp3009/${firstRepoName}`, result=>{
        console.log(result["full_name"])
    })
})
```

可能两层回调还不是那么难以阅读, 但是如果有十几层, 二十几层, 代码就人类不可读了. 这种代码被称为回调地狱(callback hell). 为了解决回调地狱, 一些语言比如 Node.js 通过运行时维护的事件队列, 允许一个异步函数在实际结果返回时不是直接运行回调, 而是把本来应该是回调的函数投入到运行时维护的事件队列中, 由运行时来执行. 同时在高层代码中抽象出 `Promise` 的概念来改善代码结构(下例中的 `asyncGet` 函数返回 `Promise`)

```javascript
asyncGet("https://api.github.com/users/czp3009/repos")
    .then(result=>{
        const firstRepoName = result[0].name
        return asyncGet(`https://api.github.com/repos/czp3009/${firstRepoName}`)
    })
    .then(result=>{
        console.log(result["full_name"])
    })
```

通过 `Promise.then` 来指定在前一个 `Promise` 执行完毕后将被投入到事件队列的函数, 并且返回值也可以是 `Promise`, 从而实现链式代码. 这样就在一定程度上解决了回调地狱.

但是这种方式也不是特别聪明, 与最初的同步代码相比, 虽然我们解决了主线程会被阻塞的问题以及采用多线程方式解决阻塞会导致线程过多的问题, 我们的代码结构依然被改变了. 新的代码结构依然会在复杂情况下增加程序员心智负担. 那么能不能有一种看起来类似同步代码的方式来实现异步呢.

答案也是有的, 请听下回分解之 [协程](../kotlin/kotlin-xie-cheng.md).
