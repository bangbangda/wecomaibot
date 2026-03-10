<?php

/**
 * 连接测试脚本
 *
 * 验证：连接 → 认证 → 心跳 → 收消息
 * 运行：php examples/test-connection.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use WeComAiBot\Event\Event;

$bot = new WeComBot([
    'bot_id' => getenv('WECOM_BOT_ID') ?: 'YOUR_BOT_ID',
    'secret' => getenv('WECOM_BOT_SECRET') ?: 'YOUR_BOT_SECRET',
]);

// 认证成功
$bot->onAuthenticated(function () {
    echo "\n=== 认证成功！机器人已上线 ===\n\n";
    echo "现在可以在企业微信中给机器人发消息进行测试\n";
    echo "机器人会原样回复你的消息\n\n";
});

// 收到消息 — echo 回复
$bot->onMessage(function (Message $message, Reply $reply) {
    echo "--- 收到消息 ---\n";
    echo "  类型: {$message->type}\n";
    echo "  会话: {$message->chatType} ({$message->chatId})\n";
    echo "  发送者: {$message->senderId}\n";
    echo "  文本: {$message->text}\n";
    if ($message->hasImages()) {
        echo "  图片: " . count($message->imageUrls) . " 张\n";
    }
    if ($message->hasQuote()) {
        echo "  引用: {$message->quoteContent}\n";
    }
    echo "----------------\n";

    // 回复
    if ($message->hasText()) {
        $reply->text("Echo: {$message->text}");
        echo "  已回复: Echo: {$message->text}\n\n";
    }
});

// 收到事件
$bot->onEvent('*', function (Event $event, Reply $reply) {
    echo "--- 收到事件 ---\n";
    echo "  类型: {$event->eventType}\n";
    echo "  发送者: {$event->senderId}\n";
    echo "----------------\n\n";
});

// 错误
$bot->onError(function (\Throwable $e) {
    echo "\n!!! 错误: {$e->getMessage()} !!!\n\n";
});

$bot->start();
