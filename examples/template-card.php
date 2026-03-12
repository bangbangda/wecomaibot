<?php

/**
 * 模板卡片消息示例
 *
 * 演示：主动推送模板卡片 + 监听按钮点击 + 更新卡片状态
 * 运行：WECOM_BOT_ID=xxx WECOM_BOT_SECRET=xxx WECOM_USER_ID=xxx php examples/template-card.php start
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

// 监听模板卡片按钮点击事件
$bot->onTemplateCardEvent(function (Event $event) use ($bot) {
    $eventKey = $event->eventData['event_key'] ?? '';
    $taskId = $event->eventData['task_id'] ?? '';

    echo "卡片按钮被点击: task_id={$taskId}, event_key={$eventKey}\n";

    // 5 秒内更新卡片状态
    $bot->updateTemplateCard($event->reqId, [
        'card_type' => 'button_interaction',
        'main_title' => [
            'title' => '服务器告警',
            'desc' => 'CPU 使用率超过 90%',
        ],
        'button_list' => [
            [
                'text' => $eventKey === 'confirm' ? '已确认' : '确认',
                'style' => 1,
                'key' => 'confirm',
            ],
            [
                'text' => $eventKey === 'false_alarm' ? '已标记误报' : '误报',
                'style' => 2,
                'key' => 'false_alarm',
            ],
        ],
        'task_id' => $taskId,
    ]);

    echo "卡片已更新\n";
});

// 收到文本消息时，推送一张模板卡片
$bot->onText(function (Message $message, Reply $reply) use ($bot) {
    if (str_contains($message->text, '告警') || str_contains($message->text, '卡片')) {
        $taskId = 'ALERT_' . time();

        $bot->pushTemplateCardToUser($message->senderId, [
            'card_type' => 'button_interaction',
            'main_title' => [
                'title' => '服务器告警',
                'desc' => 'CPU 使用率超过 90%',
            ],
            'button_list' => [
                ['text' => '确认', 'style' => 1, 'key' => 'confirm'],
                ['text' => '误报', 'style' => 2, 'key' => 'false_alarm'],
            ],
            'task_id' => $taskId,
        ]);

        echo "已推送告警卡片: task_id={$taskId}\n";
    } else {
        $reply->text("发送包含\"告警\"或\"卡片\"的消息，即可收到模板卡片。");
    }
});

$bot->onAuthenticated(function () use ($bot) {
    echo "机器人已上线\n";
    echo "发送包含\"告警\"或\"卡片\"的消息给机器人，即可收到模板卡片\n";

    // 如果设置了 WECOM_USER_ID，启动后自动推送一张测试卡片
    $userId = getenv('WECOM_USER_ID');
    if ($userId) {
        $bot->pushTemplateCardToUser($userId, [
            'card_type' => 'button_interaction',
            'main_title' => [
                'title' => '测试卡片',
                'desc' => '这是一张自动推送的模板卡片',
            ],
            'button_list' => [
                ['text' => '收到', 'style' => 1, 'key' => 'ack'],
                ['text' => '忽略', 'style' => 2, 'key' => 'ignore'],
            ],
            'task_id' => 'TEST_' . time(),
        ]);
        echo "已向 {$userId} 推送测试卡片\n";
    }
});

$bot->start();
