<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\BotManager;
use WeComAiBot\WeComBot;
use WeComAiBot\Tests\TestCase;

class BotManagerTest extends TestCase
{
    public function test_add_bot_returns_wecom_bot_instance(): void
    {
        $manager = new BotManager();

        $bot = $manager->addBot('sales', [
            'bot_id' => 'sales-bot-id',
            'secret' => 'sales-secret',
        ]);

        $this->assertInstanceOf(WeComBot::class, $bot);
    }

    public function test_get_bot_returns_registered_instance(): void
    {
        $manager = new BotManager();

        $bot = $manager->addBot('sales', [
            'bot_id' => 'sales-bot-id',
            'secret' => 'sales-secret',
        ]);

        $this->assertSame($bot, $manager->getBot('sales'));
    }

    public function test_get_bot_returns_null_for_unknown(): void
    {
        $manager = new BotManager();

        $this->assertNull($manager->getBot('nonexistent'));
    }

    public function test_duplicate_name_throws_exception(): void
    {
        $manager = new BotManager();

        $manager->addBot('sales', [
            'bot_id' => 'bot-1',
            'secret' => 'secret-1',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bot "sales" already registered');

        $manager->addBot('sales', [
            'bot_id' => 'bot-2',
            'secret' => 'secret-2',
        ]);
    }

    public function test_get_all_bots(): void
    {
        $manager = new BotManager();

        $sales = $manager->addBot('sales', [
            'bot_id' => 'sales-id',
            'secret' => 'sales-secret',
        ]);

        $finance = $manager->addBot('finance', [
            'bot_id' => 'finance-id',
            'secret' => 'finance-secret',
        ]);

        $all = $manager->getAllBots();

        $this->assertCount(2, $all);
        $this->assertSame($sales, $all['sales']);
        $this->assertSame($finance, $all['finance']);
    }

    public function test_remove_bot(): void
    {
        $manager = new BotManager();

        $manager->addBot('sales', [
            'bot_id' => 'sales-id',
            'secret' => 'sales-secret',
        ]);

        $manager->removeBot('sales');

        $this->assertNull($manager->getBot('sales'));
        $this->assertEmpty($manager->getAllBots());
    }

    public function test_remove_nonexistent_bot_is_safe(): void
    {
        $manager = new BotManager();

        // 不抛异常
        $manager->removeBot('nonexistent');

        $this->assertEmpty($manager->getAllBots());
    }

    public function test_multiple_bots_are_independent(): void
    {
        $manager = new BotManager();

        $sales = $manager->addBot('sales', [
            'bot_id' => 'sales-id',
            'secret' => 'sales-secret',
        ]);

        $finance = $manager->addBot('finance', [
            'bot_id' => 'finance-id',
            'secret' => 'finance-secret',
        ]);

        // 各自注册不同的回调，互不干扰
        $salesCalled = false;
        $financeCalled = false;

        $sales->onMessage(function () use (&$salesCalled) {
            $salesCalled = true;
        });

        $finance->onMessage(function () use (&$financeCalled) {
            $financeCalled = true;
        });

        $this->assertNotSame($sales, $finance);
    }
}
