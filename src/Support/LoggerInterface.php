<?php

declare(strict_types=1);

namespace WeComAiBot\Support;

/**
 * 日志接口
 *
 * 与 PSR-3 LoggerInterface 的子集兼容，
 * 用户可传入 PSR-3 Logger（如 Monolog）替换默认实现
 */
interface LoggerInterface
{
    public function debug(string $message): void;

    public function info(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;
}
