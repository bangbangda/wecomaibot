<?php

declare(strict_types=1);

namespace WeComAiBot\Message;

/**
 * 收到的消息对象（只读）
 *
 * 封装企微推送的消息内容，提供友好的属性访问
 */
class Message
{
    /**
     * @param string        $id          消息唯一 ID
     * @param string        $reqId       请求 ID（回复时透传）
     * @param string        $type        消息类型（text, image, voice, file, mixed）
     * @param string        $chatType    会话类型（single, group）
     * @param string        $chatId      会话 ID（群聊为 chatid，单聊为 userid）
     * @param string        $senderId    发送者 userid
     * @param string        $text        文本内容（语音消息为转文字结果）
     * @param string[]      $imageUrls   图片 URL 列表
     * @param string[]      $fileUrls    文件 URL 列表
     * @param string|null   $quoteContent 引用消息的文本内容
     * @param array<string, string> $imageAesKeys 图片 URL → AES Key 映射
     * @param array<string, string> $fileAesKeys  文件 URL → AES Key 映射
     * @param array         $raw         原始帧数据
     */
    public function __construct(
        public readonly string $id,
        public readonly string $reqId,
        public readonly string $type,
        public readonly string $chatType,
        public readonly string $chatId,
        public readonly string $senderId,
        public readonly string $text = '',
        public readonly array $imageUrls = [],
        public readonly array $fileUrls = [],
        public readonly ?string $quoteContent = null,
        public readonly array $imageAesKeys = [],
        public readonly array $fileAesKeys = [],
        public readonly array $raw = [],
    ) {
    }

    /**
     * 是否为群聊消息
     */
    public function isGroup(): bool
    {
        return $this->chatType === 'group';
    }

    /**
     * 是否为私聊消息
     */
    public function isDirect(): bool
    {
        return !$this->isGroup();
    }

    /**
     * 是否包含图片
     */
    public function hasImages(): bool
    {
        return count($this->imageUrls) > 0;
    }

    /**
     * 是否包含文件
     */
    public function hasFiles(): bool
    {
        return count($this->fileUrls) > 0;
    }

    /**
     * 是否包含引用消息
     */
    public function hasQuote(): bool
    {
        return $this->quoteContent !== null;
    }

    /**
     * 是否有文本内容
     */
    public function hasText(): bool
    {
        return $this->text !== '';
    }
}
