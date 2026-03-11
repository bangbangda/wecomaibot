<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Connection\SendQueue;
use WeComAiBot\Tests\TestCase;

class SendQueueTest extends TestCase
{
    private SendQueue $queue;
    private array $sentData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentData = [];
        $this->queue = new SendQueue($this->createLogger());
        $this->queue->setSendFn(function (string $data) {
            $this->sentData[] = $data;
        });
    }

    private function createLogger(): \WeComAiBot\Support\LoggerInterface
    {
        return new class implements \WeComAiBot\Support\LoggerInterface {
            public function debug(string $message): void {}
            public function info(string $message): void {}
            public function warning(string $message): void {}
            public function error(string $message): void {}
        };
    }

    public function test_enqueue_sends_first_item_immediately(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');

        $this->assertCount(1, $this->sentData);
        $this->assertSame('{"data":1}', $this->sentData[0]);
    }

    public function test_enqueue_holds_second_item_until_ack(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');
        $this->queue->enqueue('{"data":2}', 'req_2');

        // 只发了第一个
        $this->assertCount(1, $this->sentData);
        $this->assertSame('{"data":1}', $this->sentData[0]);

        // ack 第一个后，自动发第二个
        $this->queue->handleAck('req_1', 0);

        $this->assertCount(2, $this->sentData);
        $this->assertSame('{"data":2}', $this->sentData[1]);
    }

    public function test_serial_order_preserved(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');
        $this->queue->enqueue('{"data":2}', 'req_2');
        $this->queue->enqueue('{"data":3}', 'req_3');

        $this->queue->handleAck('req_1', 0);
        $this->queue->handleAck('req_2', 0);

        $this->assertCount(3, $this->sentData);
        $this->assertSame('{"data":1}', $this->sentData[0]);
        $this->assertSame('{"data":2}', $this->sentData[1]);
        $this->assertSame('{"data":3}', $this->sentData[2]);
    }

    public function test_on_ack_callback_receives_errcode(): void
    {
        $receivedErrcode = null;

        $this->queue->enqueue('{"data":1}', 'req_1', function (int $errcode) use (&$receivedErrcode) {
            $receivedErrcode = $errcode;
        });

        $this->queue->handleAck('req_1', 0);

        $this->assertSame(0, $receivedErrcode);
    }

    public function test_on_ack_callback_receives_error_errcode(): void
    {
        $receivedErrcode = null;

        $this->queue->enqueue('{"data":1}', 'req_1', function (int $errcode) use (&$receivedErrcode) {
            $receivedErrcode = $errcode;
        });

        $this->queue->handleAck('req_1', 40008);

        $this->assertSame(40008, $receivedErrcode);
    }

    public function test_unexpected_ack_is_ignored(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');

        // 不匹配的 ack
        $this->queue->handleAck('req_999', 0);

        // 第一个还在等待，没有发第二个
        $this->assertCount(1, $this->sentData);
    }

    public function test_clear_empties_queue(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');
        $this->queue->enqueue('{"data":2}', 'req_2');

        $this->queue->clear();

        $this->assertSame(0, $this->queue->size());

        // ack 不会再触发发送
        $this->queue->handleAck('req_1', 0);
        $this->assertCount(1, $this->sentData); // 只有第一个
    }

    public function test_size_includes_pending_and_queued(): void
    {
        $this->assertSame(0, $this->queue->size());

        $this->queue->enqueue('{"data":1}', 'req_1');
        $this->assertSame(1, $this->queue->size()); // 1 pending

        $this->queue->enqueue('{"data":2}', 'req_2');
        $this->assertSame(2, $this->queue->size()); // 1 pending + 1 queued

        $this->queue->handleAck('req_1', 0);
        $this->assertSame(1, $this->queue->size()); // 1 pending (req_2)

        $this->queue->handleAck('req_2', 0);
        $this->assertSame(0, $this->queue->size());
    }

    public function test_ack_error_still_sends_next(): void
    {
        $this->queue->enqueue('{"data":1}', 'req_1');
        $this->queue->enqueue('{"data":2}', 'req_2');

        // ack with error
        $this->queue->handleAck('req_1', 40008);

        // 即使 ack 有错误，也继续发送下一个
        $this->assertCount(2, $this->sentData);
    }

    public function test_enqueue_without_send_fn_logs_error(): void
    {
        $errors = [];
        $logger = new class($errors) implements \WeComAiBot\Support\LoggerInterface {
            private array $errors;
            public function __construct(array &$errors) { $this->errors = &$errors; }
            public function debug(string $message): void {}
            public function info(string $message): void {}
            public function warning(string $message): void {}
            public function error(string $message): void { $this->errors[] = $message; }
        };

        $queue = new SendQueue($logger);
        // 不设置 sendFn
        $queue->enqueue('{"data":1}', 'req_1');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('sendFn not set', $errors[0]);
    }
}
