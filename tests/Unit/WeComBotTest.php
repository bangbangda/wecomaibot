<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\WeComBot;
use WeComAiBot\Tests\TestCase;

class WeComBotTest extends TestCase
{
    public function test_constructor_requires_bot_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bot_id');

        new WeComBot(['secret' => 'test']);
    }

    public function test_constructor_requires_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('secret');

        new WeComBot(['bot_id' => 'test']);
    }

    public function test_constructor_accepts_valid_config(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(WeComBot::class, $bot);
    }

    public function test_fluent_api(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        // 所有 on* 方法应返回 $this 支持链式调用
        $result = $bot
            ->onMessage(fn() => null)
            ->onText(fn() => null)
            ->onImage(fn() => null)
            ->onVoice(fn() => null)
            ->onFile(fn() => null)
            ->onMixed(fn() => null)
            ->onEvent('enter_chat', fn() => null)
            ->onAuthenticated(fn() => null)
            ->onError(fn() => null);

        $this->assertSame($bot, $result);
    }

    public function test_get_client_returns_null_before_start(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $this->assertNull($bot->getClient());
    }

    public function test_push_to_user_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        // 未连接时调用不抛异常，只记录日志
        $bot->pushToUser('zhangsan', '测试');
        $this->assertTrue(true); // 没有抛异常即通过
    }

    public function test_push_to_group_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $bot->pushToGroup('group123', '测试');
        $this->assertTrue(true);
    }

    public function test_send_message_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $bot->sendMessage('zhangsan', '测试');
        $this->assertTrue(true);
    }

    public function test_push_template_card_to_user_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $bot->pushTemplateCardToUser('zhangsan', ['card_type' => 'button_interaction']);
        $this->assertTrue(true);
    }

    public function test_push_template_card_to_group_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $bot->pushTemplateCardToGroup('group123', ['card_type' => 'button_interaction']);
        $this->assertTrue(true);
    }

    public function test_update_template_card_before_connect_logs_error(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $bot->updateTemplateCard('req_123', ['card_type' => 'button_interaction']);
        $this->assertTrue(true);
    }

    public function test_on_template_card_event_fluent(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $result = $bot->onTemplateCardEvent(fn() => null);
        $this->assertSame($bot, $result);
    }
}
