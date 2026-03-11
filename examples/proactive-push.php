<?php

/**
 * 主动推送消息示例
 *
 * 认证成功后向指定用户发送消息，同时支持收到消息后回复。
 * 运行：WECOM_BOT_ID=xxx WECOM_BOT_SECRET=xxx PUSH_TO_USER=userid php examples/proactive-push.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;

$bot = new WeComBot([
    'bot_id' => getenv('WECOM_BOT_ID') ?: 'YOUR_BOT_ID',
    'secret' => getenv('WECOM_BOT_SECRET') ?: 'YOUR_BOT_SECRET',
]);

$bot->onAuthenticated(function () use ($bot) {
    echo "机器人已上线\n";

    $userId = getenv('PUSH_TO_USER');
    if ($userId) {
        // 主动推送给指定用户
        $bot->pushToUser($userId, '你好！机器人已上线，有什么需要帮忙的吗？', function (int $errcode) {
            if ($errcode === 0) {
                echo "主动推送成功\n";
            } else {
                echo "主动推送失败: errcode={$errcode}\n";
            }
        });
    }
});

// 收到消息后回复
$bot->onMessage(function (Message $message, Reply $reply) {
    echo "收到: {$message->text}\n";
    $reply->text("Echo: {$message->text}");
});

$bot->start();
