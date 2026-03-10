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
     * @param string      $id        事件唯一 ID
     * @param string      $reqId     请求 ID
     * @param string      $eventType 事件类型（enter_chat, template_card_event 等）
     * @param string      $chatType  会话类型（single, group）
     * @param string|null $chatId    会话 ID
     * @param string      $senderId  事件触发者 userid
     * @param array       $eventData 事件详细数据
     * @param array       $raw       原始帧数据
     */
    public function __construct(
        public readonly string $id,
        public readonly string $reqId,
        public readonly string $eventType,
        public readonly string $chatType,
        public readonly ?string $chatId,
        public readonly string $senderId,
        public readonly array $eventData = [],
        public readonly array $raw = [],
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
