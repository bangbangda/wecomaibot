<?php

declare(strict_types=1);

namespace WeComAiBot\Support;

/**
 * 带前缀的日志代理
 *
 * 在日志消息前添加前缀（如机器人名称），方便多 bot 场景下区分日志来源。
 */
class PrefixLogger implements LoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $inner,
        private readonly string $prefix,
    ) {
    }

    public function debug(string $message): void
    {
        $this->inner->debug($this->prefix . $message);
    }

    public function info(string $message): void
    {
        $this->inner->info($this->prefix . $message);
    }

    public function warning(string $message): void
    {
        $this->inner->warning($this->prefix . $message);
    }

    public function error(string $message): void
    {
        $this->inner->error($this->prefix . $message);
    }
}
