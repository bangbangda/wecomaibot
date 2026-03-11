<?php

declare(strict_types=1);

namespace WeComAiBot\Event;

/**
 * 企业微信事件类型枚举
 */
enum EventType: string
{
    /** 进入会话事件：用户当天首次进入机器人单聊会话 */
    case EnterChat = 'enter_chat';

    /** 模板卡片事件：用户点击模板卡片按钮 */
    case TemplateCardEvent = 'template_card_event';

    /** 用户反馈事件：用户对机器人回复进行反馈 */
    case FeedbackEvent = 'feedback_event';

    /** 断连事件：新连接建立导致旧连接被踢掉 */
    case DisconnectedEvent = 'disconnected_event';
}
