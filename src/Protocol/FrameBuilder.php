<?php

declare(strict_types=1);

namespace WeComAiBot\Protocol;

/**
 * WebSocket 帧构建工具
 *
 * 负责构建符合企微协议的 JSON 帧字符串
 */
final class FrameBuilder
{
    /**
     * 生成唯一请求 ID
     *
     * 格式：{prefix}_{timestamp}_{random}
     */
    public static function generateReqId(string $prefix): string
    {
        $timestamp = intval(microtime(true) * 1000);
        $random = bin2hex(random_bytes(4));

        return "{$prefix}_{$timestamp}_{$random}";
    }

    /**
     * 构建认证帧
     */
    public static function auth(string $botId, string $secret): string
    {
        return self::encode([
            'cmd' => Command::SUBSCRIBE,
            'headers' => ['req_id' => self::generateReqId(Command::SUBSCRIBE)],
            'body' => [
                'bot_id' => $botId,
                'secret' => $secret,
            ],
        ]);
    }

    /**
     * 构建心跳帧
     */
    public static function ping(): string
    {
        return self::encode([
            'cmd' => Command::PING,
            'headers' => ['req_id' => self::generateReqId(Command::PING)],
        ]);
    }

    /**
     * 构建流式回复帧
     *
     * @param string $reqId    原始消息的 req_id（透传）
     * @param string $streamId 流式消息 ID（同一次流式回复共用）
     * @param string $content  回复内容（支持 Markdown）
     * @param bool   $finish   是否结束流式消息
     */
    public static function replyStream(
        string $reqId,
        string $streamId,
        string $content,
        bool $finish = false,
    ): string {
        return self::encode([
            'cmd' => Command::RESPONSE,
            'headers' => ['req_id' => $reqId],
            'body' => [
                'msgtype' => 'stream',
                'stream' => [
                    'id' => $streamId,
                    'content' => $content,
                    'finish' => $finish,
                ],
            ],
        ]);
    }

    /**
     * 构建主动推送消息帧（Markdown）
     *
     * @param string $chatId   会话 ID（单聊填 userid，群聊填 chatid）
     * @param string $content  Markdown 内容
     * @param int    $chatType 会话类型：1=单聊，2=群聊，0=自动判断
     */
    public static function sendMessage(string $chatId, string $content, int $chatType = 0): string
    {
        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'markdown',
                'markdown' => ['content' => $content],
            ],
        ]);
    }

    /**
     * 构建主动推送模板卡片帧
     *
     * @param string $chatId       会话 ID（单聊填 userid，群聊填 chatid）
     * @param array  $templateCard 模板卡片结构体（透传，由调用者定义）
     * @param int    $chatType     会话类型：1=单聊，2=群聊，0=自动判断
     */
    public static function sendTemplateCard(string $chatId, array $templateCard, int $chatType = 0): string
    {
        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'template_card',
                'template_card' => $templateCard,
            ],
        ]);
    }

    /**
     * 构建更新模板卡片帧
     *
     * 收到 template_card_event 后，5 秒内调用此方法更新卡片。
     *
     * @param string $reqId        事件帧的 req_id（透传）
     * @param array  $templateCard 更新后的模板卡片结构体
     */
    public static function updateTemplateCard(string $reqId, array $templateCard): string
    {
        return self::encode([
            'cmd' => Command::RESPONSE_UPDATE,
            'headers' => ['req_id' => $reqId],
            'body' => [
                'response_type' => 'update_template_card',
                'template_card' => $templateCard,
            ],
        ]);
    }

    /**
     * 构建欢迎语回复帧（文本）
     *
     * @param string $reqId   事件帧的 req_id
     * @param string $content 欢迎语内容
     */
    public static function replyWelcome(string $reqId, string $content): string
    {
        return self::encode([
            'cmd' => Command::RESPONSE_WELCOME,
            'headers' => ['req_id' => $reqId],
            'body' => [
                'msgtype' => 'text',
                'text' => ['content' => $content],
            ],
        ]);
    }

    /**
     * 编码为 JSON 字符串
     */
    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
