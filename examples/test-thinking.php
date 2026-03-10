<?php

/**
 * 测试"思考中"加载效果
 *
 * 验证：收到消息后先发 thinking 占位符，3 秒后发最终回复
 * 运行：WECOM_BOT_ID=xxx WECOM_BOT_SECRET=xxx php examples/test-thinking.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use Workerman\Timer;

$bot = new WeComBot([
    'bot_id' => getenv('WECOM_BOT_ID') ?: 'YOUR_BOT_ID',
    'secret' => getenv('WECOM_BOT_SECRET') ?: 'YOUR_BOT_SECRET',
]);

$bot->onMessage(function (Message $message, Reply $reply) {
    echo "收到: {$message->text}\n";

    // 立即发送"思考中"占位符，触发企微加载动画
    $reply->stream('<think></think>', finish: false);
    echo "已发送 thinking 占位符\n";

    // 3 秒后发送最终回复
    Timer::add(3, function () use ($reply, $message) {
        $reply->stream("处理完成！你说的是：{$message->text}", finish: true);
        echo "已发送最终回复\n";
    }, [], false);
});

$bot->onAuthenticated(function () {
    echo "机器人已上线，发消息测试 thinking 效果\n";
});

$bot->start();
