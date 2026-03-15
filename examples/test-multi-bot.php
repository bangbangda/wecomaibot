<?php

/**
 * 多机器人实际连接测试
 *
 * 用法：php test-multi-bot.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\BotManager;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use WeComAiBot\Event\Event;

$manager = new BotManager([
    [
        'bot_id' => 'aibAK9ehSaYQKwGTBDlBu3xCKxHmCaXX2fY',
        'secret' => 'cGCXTNBroDae5xCjqWj7ywCztIpOJQBPkQL46WEFH83',
    ],
    [
        'bot_id' => 'aibv5_wFJDb9SQct5EDhfL6GyTy25AG8Vdn',
        'secret' => 'F7OfxEsnDwGeg2IprlBBIxDodnXWabSugPkePx3ohsS',
    ],
]);

// 共享 handler：通过 botId 区分来源
$sharedHandler = function (Message $message, Reply $reply) {
    $botId = $message->botId;
    $short = substr($botId, 0, 8) . '...';
    echo "=== [{$short}] 收到消息 ===\n";
    echo "  类型: {$message->type}\n";
    echo "  会话: {$message->chatType} ({$message->chatId})\n";
    echo "  发送者: {$message->senderId}\n";
    echo "  文本: {$message->text}\n";
    echo "  botId: {$botId}\n";
    echo "========================\n";

    $reply->text("[{$short}] 收到你的消息: {$message->text}");
};

// 共享事件 handler
$sharedEventHandler = function (Event $event, Reply $reply) {
    $botId = $event->botId;
    $short = substr($botId, 0, 8) . '...';
    echo "=== [{$short}] 收到事件 ===\n";
    echo "  类型: {$event->eventType}\n";
    echo "  botId: {$botId}\n";
    echo "========================\n";
};

// 两个 bot 都使用共享 handler
foreach ($manager->getAllBots() as $botId => $bot) {
    $short = substr($botId, 0, 8) . '...';

    $bot->onMessage($sharedHandler);
    $bot->onEvent('*', $sharedEventHandler);

    $bot->onAuthenticated(function () use ($short, $botId) {
        echo "[{$short}] 已上线 (botId={$botId})\n";
    });

    $bot->onError(function (\Throwable $e) use ($short) {
        echo "[{$short}] 错误: {$e->getMessage()}\n";
    });
}

echo "正在连接 2 个机器人...\n";
$manager->start();
