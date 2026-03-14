<?php

/**
 * 多机器人实例示例
 *
 * 在同一进程中运行多个企微机器人，共享 handler 通过 $message->botId 区分来源。
 *
 * 用法：php multi-bot.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\BotManager;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use WeComAiBot\Event\Event;

// 方式一：构造时传入所有 bot 配置
$manager = new BotManager([
    [
        'bot_id' => getenv('WECOM_BOT_ID_1') ?: 'your-bot-id-1',
        'secret' => getenv('WECOM_BOT_SECRET_1') ?: 'your-secret-1',
    ],
    [
        'bot_id' => getenv('WECOM_BOT_ID_2') ?: 'your-bot-id-2',
        'secret' => getenv('WECOM_BOT_SECRET_2') ?: 'your-secret-2',
    ],
]);

// 共享 handler：通过 $message->botId 区分来源
$sharedHandler = function (Message $message, Reply $reply) {
    $botId = $message->botId;
    echo "[{$botId}] 收到消息: {$message->text}\n";

    $reply->text("来自 {$botId} 的回复: {$message->text}");
};

// 所有 bot 使用同一个 handler
$manager->getBot(getenv('WECOM_BOT_ID_1') ?: 'your-bot-id-1')->onMessage($sharedHandler);
$manager->getBot(getenv('WECOM_BOT_ID_2') ?: 'your-bot-id-2')->onMessage($sharedHandler);

// 也可以为特定 bot 注册专属 handler
// $manager->getBot('your-bot-id-1')->onText(function (Message $msg, Reply $reply) {
//     $reply->text('这是 bot-1 专属的文本消息处理');
// });

// 启动（阻塞运行）
$manager->start();
