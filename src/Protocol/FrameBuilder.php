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
     * 构建主动推送图片消息帧
     *
     * @param string $chatId   会话 ID
     * @param string $mediaId  图片媒体 ID（通过上传临时素材获取）
     * @param int    $chatType 会话类型：1=单聊，2=群聊，0=自动判断
     */
    public static function sendImage(string $chatId, string $mediaId, int $chatType = 0): string
    {
        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'image',
                'image' => ['media_id' => $mediaId],
            ],
        ]);
    }

    /**
     * 构建主动推送文件消息帧
     *
     * @param string $chatId   会话 ID
     * @param string $mediaId  文件媒体 ID（通过上传临时素材获取）
     * @param int    $chatType 会话类型：1=单聊，2=群聊，0=自动判断
     */
    public static function sendFile(string $chatId, string $mediaId, int $chatType = 0): string
    {
        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'file',
                'file' => ['media_id' => $mediaId],
            ],
        ]);
    }

    /**
     * 构建主动推送语音消息帧
     *
     * @param string $chatId   会话 ID
     * @param string $mediaId  语音媒体 ID（通过上传临时素材获取）
     * @param int    $chatType 会话类型：1=单聊，2=群聊，0=自动判断
     */
    public static function sendVoice(string $chatId, string $mediaId, int $chatType = 0): string
    {
        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'voice',
                'voice' => ['media_id' => $mediaId],
            ],
        ]);
    }

    /**
     * 构建主动推送视频消息帧
     *
     * @param string      $chatId      会话 ID
     * @param string      $mediaId     视频媒体 ID（通过上传临时素材获取）
     * @param int         $chatType    会话类型：1=单聊，2=群聊，0=自动判断
     * @param string|null $title       视频标题（不超过 64 字节）
     * @param string|null $description 视频描述（不超过 512 字节）
     */
    public static function sendVideo(
        string $chatId,
        string $mediaId,
        int $chatType = 0,
        ?string $title = null,
        ?string $description = null,
    ): string {
        $video = ['media_id' => $mediaId];
        if ($title !== null) {
            $video['title'] = $title;
        }
        if ($description !== null) {
            $video['description'] = $description;
        }

        return self::encode([
            'cmd' => Command::SEND_MSG,
            'headers' => ['req_id' => self::generateReqId(Command::SEND_MSG)],
            'body' => [
                'chatid' => $chatId,
                'chat_type' => $chatType,
                'msgtype' => 'video',
                'video' => $video,
            ],
        ]);
    }

    // ========== 临时素材上传 ==========

    /**
     * 构建上传临时素材初始化帧
     *
     * @param string      $type        文件类型：image, voice, video, file
     * @param string      $filename    文件名
     * @param int         $totalSize   文件总大小（字节）
     * @param int         $totalChunks 分片数量
     * @param string|null $md5         文件 MD5（可选，服务端校验完整性）
     */
    public static function uploadMediaInit(
        string $type,
        string $filename,
        int $totalSize,
        int $totalChunks,
        ?string $md5 = null,
    ): string {
        $body = [
            'type' => $type,
            'filename' => $filename,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
        ];
        if ($md5 !== null) {
            $body['md5'] = $md5;
        }

        return self::encode([
            'cmd' => Command::UPLOAD_INIT,
            'headers' => ['req_id' => self::generateReqId(Command::UPLOAD_INIT)],
            'body' => $body,
        ]);
    }

    /**
     * 构建上传分片帧
     *
     * @param string $uploadId   上传 ID（init 返回）
     * @param int    $chunkIndex 分片序号（从 0 开始）
     * @param string $base64Data 分片内容的 Base64 编码
     */
    public static function uploadMediaChunk(string $uploadId, int $chunkIndex, string $base64Data): string
    {
        return self::encode([
            'cmd' => Command::UPLOAD_CHUNK,
            'headers' => ['req_id' => self::generateReqId(Command::UPLOAD_CHUNK)],
            'body' => [
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'base64_data' => $base64Data,
            ],
        ]);
    }

    /**
     * 构建上传完成帧
     *
     * @param string $uploadId 上传 ID（init 返回）
     */
    public static function uploadMediaFinish(string $uploadId): string
    {
        return self::encode([
            'cmd' => Command::UPLOAD_FINISH,
            'headers' => ['req_id' => self::generateReqId(Command::UPLOAD_FINISH)],
            'body' => [
                'upload_id' => $uploadId,
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
