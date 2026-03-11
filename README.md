# WeComAiBot

**企业微信 AI 机器人 PHP SDK** — 基于 WebSocket 长连接，免回调地址配置。

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

> PHP 是世界上最好的语言。Node.js 有的 SDK，PHP 必须也得有。
>
> 别人用回调地址、公网 IP、SSL 证书搞得焦头烂额的时候，我们 PHP 开发者只需要一行 `composer require`，三行代码，原地起飞。

---

## 这是什么？

企业微信官方提供了 AI 机器人的 WebSocket 长连接通道（`wss://openws.work.weixin.qq.com`），Node.js 有官方 SDK（`@wecom/aibot-node-sdk`），但 PHP 生态一片空白。

**WeComAiBot** 填补了这个空白。它是企业微信 AI 机器人的 PHP SDK，基于 [Workerman](https://www.workerman.net/) 实现 WebSocket 客户端长连接。

**翻译成人话就是：**
- 不用配置回调地址（不需要公网 IP、域名、SSL 证书）
- 不用处理 5 秒超时（WebSocket 没有这个限制）
- 不用搞消息队列（长连接天然异步）
- 在内网、本地、开发机上都能跑
- `composer require` 装完就能用

## 特性

- **零门槛接入** — 免回调地址，免 SSL，免公网 IP，内网也能跑
- **三行代码启动** — 配置 bot_id + secret，注册回调，`start()`，完事
- **流式回复** — 支持"思考中"加载动画 → 逐步更新 → 最终回复，就像 ChatGPT 那样
- **主动推送** — 不用等用户说话，机器人可以主动找人聊天
- **全消息类型** — 文本、语音(转文字)、图片、文件、图文混排、引用消息，全都支持
- **断线自动重连** — 指数退避 + 随机抖动，心跳保活，网不好也不怕
- **串行发送队列** — 帧发送后等 ack 再发下一帧，超时自动跳过，告别消息丢失和乱序
- **多机器人管理** — 一个进程跑多个 bot，各自独立连接、独立重连，数据完全隔离
- **Laravel 深度集成** — Service Provider 自动注册，`php artisan wecom:serve` 一键启动
- **纯 PHP 实现** — 基于 Workerman，不需要装 Swoole 扩展，`composer require` 就行

## 安装

```bash
composer require bangbangda/wecomaibot
```

就这么简单。没有第二步。

## 快速开始

### 纯 PHP（任何项目都能用）

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;

$bot = new WeComBot([
    'bot_id' => 'your-bot-id',     // 企微后台获取
    'secret' => 'your-secret',     // 企微后台获取
]);

// 收到消息，回复
$bot->onMessage(function (Message $message, Reply $reply) {
    $reply->text("你好！你说的是：{$message->text}");
});

$bot->start();
```

```bash
php your-bot.php start
```

没了。三行核心代码，机器人就活了。

### Laravel

**1. 发布配置：**

```bash
php artisan vendor:publish --tag=wecomaibot-config
```

**2. 配置 `.env`：**

```env
WECOM_BOT_ID=your-bot-id
WECOM_BOT_SECRET=your-bot-secret
```

**3. 写一个 Handler：**

```php
// app/Services/WeComHandler.php
namespace App\Services;

use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;

class WeComHandler
{
    public function onMessage(Message $message, Reply $reply): void
    {
        $reply->text("Echo: {$message->text}");
    }
}
```

**4. 在配置中指定：**

```php
// config/wecomaibot.php
'handler' => \App\Services\WeComHandler::class,
```

**5. 启动：**

```bash
php artisan wecom:serve
```

## 流式回复（"思考中"效果）

像 ChatGPT 一样，先显示加载动画，再逐步输出回复内容：

```php
use Workerman\Timer;

$bot->onMessage(function (Message $message, Reply $reply) {
    // 第 1 步：立即发送"思考中"，企微显示加载动画
    $reply->stream('<think></think>', finish: false);

    // 第 2 步：调用 AI 接口（这里用 Timer 模拟延迟）
    Timer::add(2, function () use ($reply, $message) {
        // 第 3 步：发送最终回复，加载动画消失
        $reply->stream("关于「{$message->text}」，我的回答是...", finish: true);
    }, [], false);
});
```

> **注意：** Workerman 事件循环中不能用 `sleep()`，要用 `Timer::add()` 实现延迟。

## 主动推送消息

不用等用户说话，机器人主动找人。支持单聊和群聊，自动区分会话类型：

```php
$bot->onAuthenticated(function () use ($bot) {
    // 推送给指定用户（单聊，chat_type=1）
    $bot->pushToUser('zhangsan', '你好，提醒你下午 3 点有个会议');

    // 推送到群聊（chat_type=2）
    $bot->pushToGroup('group-chat-id', '各位注意，系统将在 5 分钟后维护');

    // 自动判断（chat_type=0，兼容旧代码）
    $bot->sendMessage('zhangsan', '这条消息会自动判断会话类型');
});
```

> **前提：** 用户需先在会话中给机器人发过消息，机器人才能主动推送。
>
> **频率限制：** 30 条/分钟，1000 条/小时（与回复消息共享配额）。

**监听发送结果（ack 回调）：**

```php
$bot->pushToUser('zhangsan', '重要通知', function (int $errcode) {
    if ($errcode === 0) {
        echo "发送成功\n";
    } elseif ($errcode === -1) {
        echo "发送超时\n";
    } else {
        echo "发送失败: errcode={$errcode}\n";
    }
});
```

## 监听特定消息类型

```php
// 只处理文本
$bot->onText(function (Message $message, Reply $reply) {
    $reply->text("收到文本：{$message->text}");
});

// 只处理图片
$bot->onImage(function (Message $message, Reply $reply) {
    $reply->text("收到 " . count($message->imageUrls) . " 张图片");
});

// 只处理语音（企微已自动转文字）
$bot->onVoice(function (Message $message, Reply $reply) {
    $reply->text("语音转文字：{$message->text}");
});

// 只处理文件
$bot->onFile(function (Message $message, Reply $reply) {
    $reply->text("收到 " . count($message->fileUrls) . " 个文件");
});

// 图文混排
$bot->onMixed(function (Message $message, Reply $reply) {
    $reply->text("收到图文消息");
});
```

## 事件监听

```php
use WeComAiBot\Event\Event;

// 用户首次进入会话
$bot->onEvent('enter_chat', function (Event $event, Reply $reply) {
    $reply->text("你好！我是 AI 助手，有什么可以帮你的？");
});

// 监听所有事件
$bot->onEvent('*', function (Event $event, Reply $reply) {
    echo "事件类型：{$event->eventType}\n";
    echo "事件时间：" . date('Y-m-d H:i:s', $event->createTime) . "\n";
});
```

> **超时约束：** `enter_chat` 欢迎语需在 **5 秒内**回复，流式消息需在 **6 分钟内**完成，回复消息有效期 **24 小时**。

## Message 对象

收到消息后，`Message` 对象提供以下属性：

| 属性 | 类型 | 说明 |
|------|------|------|
| `$message->id` | string | 消息唯一 ID |
| `$message->type` | string | 消息类型 (text/image/voice/file/mixed) |
| `$message->chatType` | string | 会话类型 (single/group) |
| `$message->chatId` | string | 会话 ID |
| `$message->senderId` | string | 发送者 userid |
| `$message->text` | string | 文本内容（语音消息为转文字结果） |
| `$message->imageUrls` | string[] | 图片 URL 列表 |
| `$message->fileUrls` | string[] | 文件 URL 列表 |
| `$message->quoteContent` | ?string | 引用消息的文本内容 |

**便捷方法：**

```php
$message->isGroup();    // 是否群聊
$message->isDirect();   // 是否私聊
$message->hasText();    // 是否有文本
$message->hasImages();  // 是否有图片
$message->hasFiles();   // 是否有文件
$message->hasQuote();   // 是否有引用
```

## 发送回执（ack 回调）

所有发送的消息都经过串行队列，等待企微服务端确认后再发下一条。回复消息同样支持 ack 回调：

```php
$bot->onMessage(function (Message $message, Reply $reply) {
    $reply->stream('处理完成！', finish: true, onAck: function (int $errcode) {
        echo "回复ack: {$errcode}\n";
    });
});
```

## 多机器人管理

一个进程同时跑多个机器人，每个 bot 拥有独立的连接、心跳、重连和发送队列，数据完全隔离：

```php
use WeComAiBot\BotManager;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;

$manager = new BotManager();

// 销售部机器人
$salesBot = $manager->addBot('sales', [
    'bot_id' => 'sales-bot-id',
    'secret' => 'sales-bot-secret',
]);
$salesBot->onMessage(function (Message $message, Reply $reply) {
    $reply->text("【销售助手】收到：{$message->text}");
});

// 财务部机器人
$financeBot = $manager->addBot('finance', [
    'bot_id' => 'finance-bot-id',
    'secret' => 'finance-bot-secret',
]);
$financeBot->onMessage(function (Message $message, Reply $reply) {
    $reply->text("【财务助手】收到：{$message->text}");
});

// 一键启动所有机器人
$manager->start();
```

```bash
php multi-bot.php start
```

**BotManager API：**

```php
$manager->addBot('name', $config);   // 注册机器人，返回 WeComBot 实例
$manager->getBot('name');            // 获取已注册的机器人
$manager->getAllBots();              // 获取所有机器人
$manager->removeBot('name');         // 移除机器人
$manager->start();                   // 启动所有机器人（阻塞）
```

> **隔离保证：** 每个 bot 有独立的 WebSocket 连接，企微服务端只推送该 bot 的消息到对应连接。多 bot 同时断线时，重连采用随机抖动避免冲击服务端。一个 bot 认证失败不影响其他 bot。

## 完整配置项

```php
$bot = new WeComBot([
    'bot_id'                 => 'xxx',          // (必填) 机器人 ID
    'secret'                 => 'xxx',          // (必填) 机器人 Secret
    'ws_url'                 => 'wss://...',    // (可选) WebSocket 地址，默认官方地址
    'heartbeat_interval'     => 30,             // (可选) 心跳间隔秒数，默认 30
    'max_reconnect_attempts' => 100,            // (可选) 最大重连次数，-1 为无限
    'ack_timeout'            => 10,             // (可选) 发送帧 ack 超时秒数，默认 10
    'logger'                 => $myLogger,      // (可选) 自定义日志，实现 LoggerInterface
]);
```

## 自定义日志

默认输出到控制台。你可以传入自己的日志实现（兼容 PSR-3 子集）：

```php
use WeComAiBot\Support\LoggerInterface;

class MyLogger implements LoggerInterface
{
    public function debug(string $message): void { /* ... */ }
    public function info(string $message): void  { /* ... */ }
    public function warning(string $message): void { /* ... */ }
    public function error(string $message): void { /* ... */ }
}

$bot = new WeComBot([
    'bot_id' => 'xxx',
    'secret' => 'xxx',
    'logger' => new MyLogger(),
]);
```

## 生产部署

机器人是常驻进程，推荐用 Supervisor 守护：

```ini
# /etc/supervisor/conf.d/wecom-bot.conf
[program:wecom-bot]
command=php /path/to/your-bot.php start
# Laravel 项目用这个：
# command=php /path/to/artisan wecom:serve
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/wecom-bot.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start wecom-bot
```

Workerman 也支持自带的守护进程模式：

```bash
php your-bot.php start -d   # 后台运行
php your-bot.php status     # 查看状态
php your-bot.php stop       # 停止
php your-bot.php restart    # 重启
```

## 技术栈

| 组件 | 说明 |
|------|------|
| [Workerman](https://www.workerman.net/) | PHP 高性能异步框架，提供 WebSocket 客户端和事件循环 |
| AsyncTcpConnection | Workerman 的异步 TCP 连接，支持 ws:// 和 wss:// |
| Timer | Workerman 定时器，用于心跳和重连 |

## 为什么不用 XXX？

| 方案 | 问题 |
|------|------|
| HTTP 回调 | 需要公网 IP + 域名 + SSL 证书 + 处理 5 秒超时 |
| Swoole | 需要安装 C 扩展，部署门槛高 |
| ratchet/pawl | 没有内置重连和心跳，要自己造轮子 |
| Node.js SDK | 很好，但我们是 PHP 开发者，我们用 PHP（PHP 是最好的语言.jpg） |

## Roadmap

- [x] WebSocket 连接 + 认证 + 心跳 + 断线重连
- [x] 消息收发（文本/语音/图片/文件/混排/引用）
- [x] 流式回复（"思考中"加载效果）
- [x] 主动推送消息
- [x] 事件监听（enter_chat 等）
- [x] Laravel Service Provider + Artisan 命令
- [ ] 模板卡片消息
- [ ] 流式 + 卡片组合回复
- [ ] 文件下载 + AES-256-CBC 解密
- [x] 回复消息回执等待（串行队列 + ack 回调）
- [x] 多机器人实例管理（BotManager，数据完全隔离）

## 参与贡献

Issue 和 PR 都欢迎。

让 PHP 更好玩，让开发者更开心。

## 开源协议

[MIT](LICENSE)

---

> **"PHP is not dead. PHP is just getting started."**
>
> — 每一个还在写 PHP 的开发者
