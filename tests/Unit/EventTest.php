<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Event\Event;
use WeComAiBot\Event\EventType;
use WeComAiBot\Tests\TestCase;

class EventTest extends TestCase
{
    public function test_event_type_enum_covers_all_official_types(): void
    {
        $this->assertSame('enter_chat', EventType::EnterChat->value);
        $this->assertSame('template_card_event', EventType::TemplateCardEvent->value);
        $this->assertSame('feedback_event', EventType::FeedbackEvent->value);
        $this->assertSame('disconnected_event', EventType::DisconnectedEvent->value);
    }

    public function test_event_type_from_string(): void
    {
        $this->assertSame(EventType::DisconnectedEvent, EventType::from('disconnected_event'));
    }

    public function test_event_create_time(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'enter_chat',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
            createTime: 1700000000,
        );

        $this->assertSame(1700000000, $event->createTime);
    }

    public function test_event_create_time_nullable(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'enter_chat',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
        );

        $this->assertNull($event->createTime);
    }

    public function test_event_type_method_returns_enum(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'disconnected_event',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
        );

        $this->assertSame(EventType::DisconnectedEvent, $event->type());
    }

    public function test_event_type_method_returns_null_for_unknown(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'unknown_event',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
        );

        $this->assertNull($event->type());
    }

    public function test_event_bot_id(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'enter_chat',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
            botId: 'my-bot-id',
        );

        $this->assertSame('my-bot-id', $event->botId);
    }

    public function test_event_bot_id_defaults_to_empty(): void
    {
        $event = new Event(
            id: 'msg1',
            reqId: 'req1',
            eventType: 'enter_chat',
            chatType: 'single',
            chatId: null,
            senderId: 'user1',
        );

        $this->assertSame('', $event->botId);
    }
}
