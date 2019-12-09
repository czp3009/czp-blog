# Telegram Bot 收不到普通群聊消息的问题

当我们写好一个 `Telegram Bot` 之后, 把它拉到一个群里, 然后在群里发一条命令, 机器人马上就收到了命令.

但是我们在群里发送一个普通消息\(不以 `/` 开头的消息\), 我们发现机器人没有收到这条消息.

我们私聊机器人, 机器人是能收到消息的. 换一个群, 依然收不到普通群聊消息, 但是能收到群里发的命令\(命令一定是以 `/` 开头的\).

这个问题是由于 `Telegram Bot` 的 `privacy` 设置问题导致的. 详见 [https://core.telegram.org/bots\#privacy-mode](https://core.telegram.org/bots#privacy-mode)

更改 `privacy mode` 非常简单, 首先我们联系 `BotFather` [https://telegram.me/BotFather](https://telegram.me/BotFather)

然后输入命令

```text
/setprivacy
```

选择自己的机器人, 然后选择 `Disable`.

就可以将自己的机器人的 `privacy mode` 设置为 `DISABLED`.

但是我们发现我们的机器人依然收不到消息.

我们将机器人踢出群, 再拉进群, 就可以收到消息了.
