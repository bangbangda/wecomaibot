<?php

declare(strict_types=1);

namespace WeComAiBot\Message;

/**
 * 消息解析器
 *
 * 将企微推送的原始 WebSocket 帧解析为 Message 对象
 */
class MessageParser
{
    /**
     * 从原始帧数据解析出 Message 对象
     *
     * @param array  $frame 原始 WebSocket 帧（已 json_decode）
     * @param string $botId 接收此消息的机器人 ID
     * @return Message|null 解析失败返回 null
     */
    public static function parse(array $frame, string $botId = ''): ?Message
    {
        $body = $frame['body'] ?? [];
        $headers = $frame['headers'] ?? [];

        if (empty($body) || empty($body['msgtype'])) {
            return null;
        }

        $reqId = $headers['req_id'] ?? '';
        $msgId = $body['msgid'] ?? '';
        $msgType = $body['msgtype'] ?? '';
        $chatType = ($body['chattype'] ?? 'single') === 'group' ? 'group' : 'single';
        $chatId = $body['chatid'] ?? ($body['from']['userid'] ?? '');
        $senderId = $body['from']['userid'] ?? '';

        // 解析消息内容
        $parsed = self::parseContent($body);

        // 群聊中移除 @机器人 的提及标记
        $text = $parsed['text'];
        if ($chatType === 'group') {
            $text = trim(preg_replace('/@\S+/', '', $text));
        }

        // 如果文本为空但存在引用消息，使用引用消息内容
        if ($text === '' && $parsed['quoteContent'] !== null) {
            $text = $parsed['quoteContent'];
        }

        return new Message(
            id: $msgId,
            reqId: $reqId,
            type: $msgType,
            chatType: $chatType,
            chatId: $chatId,
            senderId: $senderId,
            text: $text,
            imageUrls: $parsed['imageUrls'],
            fileUrls: $parsed['fileUrls'],
            videoUrls: $parsed['videoUrls'],
            quoteContent: $parsed['quoteContent'],
            imageAesKeys: $parsed['imageAesKeys'],
            fileAesKeys: $parsed['fileAesKeys'],
            videoAesKeys: $parsed['videoAesKeys'],
            raw: $frame,
            botId: $botId,
        );
    }

    /**
     * 解析消息体中的内容（文本、图片、文件、引用）
     *
     * 支持：文本、语音（转文字）、图片、文件、图文混排、引用消息
     *
     * @return array{text: string, imageUrls: string[], fileUrls: string[], videoUrls: string[], quoteContent: ?string, imageAesKeys: array, fileAesKeys: array, videoAesKeys: array}
     */
    public static function parseContent(array $body): array
    {
        $textParts = [];
        $imageUrls = [];
        $imageAesKeys = [];
        $fileUrls = [];
        $fileAesKeys = [];
        $videoUrls = [];
        $videoAesKeys = [];
        $quoteContent = null;

        $msgType = $body['msgtype'] ?? '';

        // 处理图文混排消息
        if ($msgType === 'mixed' && isset($body['mixed']['msg_item'])) {
            foreach ($body['mixed']['msg_item'] as $item) {
                if (($item['msgtype'] ?? '') === 'text' && !empty($item['text']['content'])) {
                    $textParts[] = $item['text']['content'];
                } elseif (($item['msgtype'] ?? '') === 'image' && !empty($item['image']['url'])) {
                    $imageUrls[] = $item['image']['url'];
                    if (!empty($item['image']['aeskey'])) {
                        $imageAesKeys[$item['image']['url']] = $item['image']['aeskey'];
                    }
                }
            }
        } else {
            // 单条消息
            if (!empty($body['text']['content'])) {
                $textParts[] = $body['text']['content'];
            }

            // 语音消息（语音转文字）
            if ($msgType === 'voice' && !empty($body['voice']['content'])) {
                $textParts[] = $body['voice']['content'];
            }

            // 图片消息
            if (!empty($body['image']['url'])) {
                $imageUrls[] = $body['image']['url'];
                if (!empty($body['image']['aeskey'])) {
                    $imageAesKeys[$body['image']['url']] = $body['image']['aeskey'];
                }
            }

            // 文件消息
            if ($msgType === 'file' && !empty($body['file']['url'])) {
                $fileUrls[] = $body['file']['url'];
                if (!empty($body['file']['aeskey'])) {
                    $fileAesKeys[$body['file']['url']] = $body['file']['aeskey'];
                }
            }

            // 视频消息
            if ($msgType === 'video' && !empty($body['video']['url'])) {
                $videoUrls[] = $body['video']['url'];
                if (!empty($body['video']['aeskey'])) {
                    $videoAesKeys[$body['video']['url']] = $body['video']['aeskey'];
                }
            }
        }

        // 处理引用消息
        if (isset($body['quote'])) {
            $quote = $body['quote'];
            $quoteType = $quote['msgtype'] ?? '';

            if ($quoteType === 'text' && !empty($quote['text']['content'])) {
                $quoteContent = $quote['text']['content'];
            } elseif ($quoteType === 'voice' && !empty($quote['voice']['content'])) {
                $quoteContent = $quote['voice']['content'];
            } elseif ($quoteType === 'image' && !empty($quote['image']['url'])) {
                $imageUrls[] = $quote['image']['url'];
                if (!empty($quote['image']['aeskey'])) {
                    $imageAesKeys[$quote['image']['url']] = $quote['image']['aeskey'];
                }
            } elseif ($quoteType === 'file' && !empty($quote['file']['url'])) {
                $fileUrls[] = $quote['file']['url'];
                if (!empty($quote['file']['aeskey'])) {
                    $fileAesKeys[$quote['file']['url']] = $quote['file']['aeskey'];
                }
            } elseif ($quoteType === 'video' && !empty($quote['video']['url'])) {
                $videoUrls[] = $quote['video']['url'];
                if (!empty($quote['video']['aeskey'])) {
                    $videoAesKeys[$quote['video']['url']] = $quote['video']['aeskey'];
                }
            }
        }

        $text = trim(implode("\n", $textParts));

        return compact('text', 'imageUrls', 'fileUrls', 'videoUrls', 'quoteContent', 'imageAesKeys', 'fileAesKeys', 'videoAesKeys');
    }
}
