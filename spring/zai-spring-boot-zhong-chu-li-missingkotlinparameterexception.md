# 在 Spring Boot 中处理 MissingKotlinParameterException

本文撰写时的 spring 版本: Spring Boot 2.5.5.RELEASE

众所周知 kotlin 有空安全功能, 不能将一个非空类型变量赋值为 null. 这在正常代码中是没有问题的. 但是如果变量是通过反射进行赋值的, 比如说反序列化库, 诸如 jackson, gson 等就会有问题.

如果反序列化库直接用 Unsafe 来生成实例, 最终会得到一个空了的非空字段. 如果反序列化库用构造器来初始化, 将抛出 NullPointerException. 而如果反序列化库支持使用 kotlin 语言, 将会有对应的异常. 例如 jackson 在安装了 [kotlin module](https://github.com/FasterXML/jackson-module-kotlin) 后将对传入的实际值是 null, 但是字段类型不可空的成员抛出 `MissingKotlinParameterException` .

如果只是使用反序列化库, 这一切还不是特别大的问题, 简单地 try catch 就可以处理异常了. 但是如果是在 spring 中碰到这个问题, 反序列化的代码在框架里, 这下 try 不了了. 而 spring 是针对 java 编写的, 一旦反序列化器(默认为 jackson)抛出异常, 解码 HTTP 数据这整一步就会抛出 `DecodingException` 来. 更要命的是这一异常还会被包装成 `ServerWebInputException` , 内含硬编码写死的 message: "Failed to read HTTP message". 最终的 ErrorAttribute 无论到底是哪个字段为空了, 都是一模一样的:

```json
{
  "timestamp": "xxx",
  "path": "xxx",
  "status": 400,
  "error": "Bad Request",
  "message": "Failed to read HTTP message",
  "requestId": "xxx"
}
```

毕竟对 java 而言, 如果反序列化失败了, 一定是 json 语法错误. spring 从未考虑过还会有别的情况会导致反序列化库抛出异常.

为了解决这个问题, 我们需要将 `MissingKotlinParameterException` (这是来自 jackson 的异常, 如果使用别的序列化库请用别的异常作为捕获条件)里包含的实际 message 提取出来作为最终的异常的 message.

```kotlin
@RestControllerAdvice
class JacksonMissingKotlinParameterControllerAdvice() {
    @ExceptionHandler(MissingKotlinParameterException::class)
    fun missingKotlinParameterAdvice(exception: Exception) {
        val actualException = exception.cause?.cause
        if (exception is ServerWebInputException && actualException is MissingKotlinParameterException) {
            throw ServerWebInputException(
                actualException.message ?: exception.reason,
                exception.methodParameter,
                exception.cause
            )
        } else {
            throw exception
        }
    }
}
```

实际上的异常堆栈是这样的: `ServerWebInputException` -> `DecodingException` -> `MissingKotlinParameterException`, 所以 cause 要跨两层. 而不符合这一结构和类型的异常就不是目标异常, 直接重新抛出它. 对于目标异常, 由于 `ServerWebInputException` 内部的字段不可修改, 所以创建一个新的, 除了 message 之外全部拷贝过去, message 用 `MissingKotlinParameterException` 里的 message, 就可以让最终返回给客户端的 message 包含到底是哪个字段错了的信息(在 ControllerAdvice 里抛出的异常将通过正常异常处理流程重新处理一遍):

```json
{
  "timestamp": "xxx",
  "path": "xxx",
  "status": 400,
  "error": "Bad Request",
  "message": "Instantiation of [simple type, class xxx] value failed for JSON property xxx due to missing (therefore NULL) value for creator parameter xxx which is a non-nullable type\n at [Source: (io.netty.buffer.ByteBufInputStream); line: 1, column: 2] (through reference chain: xxx[\"xxx\"])",
  "requestId": "xxx"
}
```

虽然这样的 message 可能还需要进一步处理, 但是至少不是无论哪个字段错了都返回一样的消息了.
