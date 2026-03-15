<?php

declare(strict_types=1);

namespace WeComAiBot\Event;

/**
 * 事件对象（只读）
 *
 * 封装企微推送的事件回调内容
 */
class Event
{
    /**
     * @param string      $id         事件唯一 ID
     * @param string      $reqId      请求 ID
     * @param string      $eventType  事件类型（enter_chat, template_card_event 等）
     * @param string      $chatType   会话类型（single, group）
     * @param string|null $chatId     会话 ID
     * @param string      $senderId   事件触发者 userid
     * @param int|null    $createTime 事件创建时间（Unix 时间戳）
     * @param array       $eventData  事件详细数据
     * @param array       $raw        原始帧数据
     * @param string      $botId      接收此事件的机器人 ID（多 bot 场景下区分来源）
     */
    public function __construct(
        public readonly string $id,
        public readonly string $reqId,
        public readonly string $eventType,
        public readonly string $chatType,
        public readonly ?string $chatId,
        public readonly string $senderId,
        public readonly ?int $createTime = null,
        public readonly array $eventData = [],
        public readonly array $raw = [],
        public readonly string $botId = '',
    ) {
    }

    /**
     * 获取事件类型枚举（未知类型返回 null）
     */
    public function type(): ?EventType
    {
        return EventType::tryFrom($this->eventType);
    }
}
