<?php

/**
 * 流式回复示例
 *
 * 模拟 AI 思考过程，先发送"思考中"，再逐步更新回复内容
 *
 * 运行：php examples/stream-reply.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use Workerman\Timer;

$bot = new WeComBot([
    'bot_id' => 'YOUR_BOT_ID',
    'secret' => 'YOUR_BOT_SECRET',
]);

$bot->onMessage(function (Message $message, Reply $reply) {
    // 第一步：发送"思考中"（finish: false 表示还没结束）
    $reply->stream('正在思考中...', finish: false);

    // 模拟 AI 处理延迟，实际使用时替换为 AI 接口调用
    // 注意：Workerman 事件循环中不能用 sleep()，要用 Timer
    Timer::add(1, function () use ($reply, $message) {
        // 第二步：发送中间结果
        $reply->stream("正在处理你的问题：{$message->text}\n\n请稍候...", finish: false);
    }, [], false);

    Timer::add(2, function () use ($reply, $message) {
        // 第三步：发送最终结果（finish: true 结束流式消息）
        $reply->stream("你好！你问的是：「{$message->text}」\n\n这是我的回答。", finish: true);
    }, [], false);
});

$bot->onAuthenticated(function () {
    echo "✅ 机器人已上线！\n";
});

$bot->start();
