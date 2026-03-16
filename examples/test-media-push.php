<?php

/**
 * 媒体消息推送测试
 *
 * 用法：php test-media-push.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;

$bot = new WeComBot([
    'bot_id' => 'aibAK9ehSaYQKwGTBDlBu3xCKxHmCaXX2fY',
    'secret' => 'cGCXTNBroDae5xCjqWj7ywCztIpOJQBPkQL46WEFH83',
]);

$bot->onAuthenticated(function () use ($bot) {
    echo "已上线，3 秒后推送图片...\n";

    \Workerman\Timer::add(3, function () use ($bot) {
        echo "正在推送图片...\n";
        $bot->pushImageToUser('HaoLiang', '/tmp/test-image.png', function (int $errcode) {
            echo "图片推送结果: errcode={$errcode}\n";
        });
    }, [], false);
});

$bot->onError(function (\Throwable $e) {
    echo "错误: {$e->getMessage()}\n";
});

$bot->start();
