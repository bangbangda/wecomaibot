<?php

/**
 * 基础示例 — 最简单的 Echo 机器人
 *
 * 运行：php examples/basic.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;

$bot = new WeComBot([
    'bot_id' => 'YOUR_BOT_ID',      // 替换为你的机器人 ID
    'secret' => 'YOUR_BOT_SECRET',   // 替换为你的机器人 Secret
]);

// 收到任何消息，原样回复
$bot->onMessage(function (Message $message, Reply $reply) {
    echo "收到消息: {$message->text}\n";
    $reply->text("你说的是：{$message->text}");
});

// 认证成功
$bot->onAuthenticated(function () {
    echo "✅ 机器人已上线！\n";
});

// 错误处理
$bot->onError(function (\Throwable $e) {
    echo "❌ 错误: {$e->getMessage()}\n";
});

$bot->start();
