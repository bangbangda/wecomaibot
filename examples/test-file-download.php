<?php

/**
 * 测试文件下载 + 解密
 *
 * 发送图片或文件给机器人，机器人会下载解密并回复文件信息。
 * 运行：WECOM_BOT_ID=xxx WECOM_BOT_SECRET=xxx php examples/test-file-download.php start
 */

require __DIR__ . '/../vendor/autoload.php';

use WeComAiBot\WeComBot;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use WeComAiBot\Media\MediaDownloader;

$bot = new WeComBot([
    'bot_id' => getenv('WECOM_BOT_ID') ?: 'YOUR_BOT_ID',
    'secret' => getenv('WECOM_BOT_SECRET') ?: 'YOUR_BOT_SECRET',
]);

$downloader = new MediaDownloader();

// 收到图片
$bot->onImage(function (Message $message, Reply $reply) use ($downloader) {
    echo "收到图片消息，共 " . count($message->imageUrls) . " 张\n";

    foreach ($message->imageUrls as $url) {
        $aesKey = $message->imageAesKeys[$url] ?? null;
        echo "  URL: {$url}\n";
        echo "  AES Key: " . ($aesKey ?: '无') . "\n";

        try {
            $savedPath = $downloader->downloadToFile($url, '/tmp/', $aesKey);
            $size = filesize($savedPath);
            echo "  保存成功: {$savedPath} ({$size} 字节)\n";
            $reply->text("收到图片！已保存到: {$savedPath}\n大小: {$size} 字节");
        } catch (\Throwable $e) {
            echo "  下载失败: {$e->getMessage()}\n";
            $reply->text("图片下载失败: {$e->getMessage()}");
        }
    }
});

// 收到文件
$bot->onFile(function (Message $message, Reply $reply) use ($downloader) {
    echo "收到文件消息，共 " . count($message->fileUrls) . " 个\n";

    foreach ($message->fileUrls as $url) {
        $aesKey = $message->fileAesKeys[$url] ?? null;
        echo "  URL: {$url}\n";
        echo "  AES Key: " . ($aesKey ?: '无') . "\n";

        try {
            $savedPath = $downloader->downloadToFile($url, '/tmp/', $aesKey);
            $size = filesize($savedPath);
            echo "  保存成功: {$savedPath} ({$size} 字节)\n";
            $reply->text("收到文件！已保存到: {$savedPath}\n大小: {$size} 字节");
        } catch (\Throwable $e) {
            echo "  下载失败: {$e->getMessage()}\n";
            $reply->text("文件下载失败: {$e->getMessage()}");
        }
    }
});

// 收到文本
$bot->onText(function (Message $message, Reply $reply) {
    $reply->text("Echo: {$message->text}\n\n发送图片或文件给我，测试下载解密功能。");
});

$bot->onAuthenticated(function () {
    echo "机器人已上线，发送图片或文件进行测试\n";
});

$bot->start();
