<?php

declare(strict_types=1);

namespace WeComAiBot\Protocol;

/**
 * 企业微信 WebSocket 命令常量
 *
 * 定义所有 WebSocket 帧的 cmd 字段值
 */
final class Command
{
    // ========== 开发者 → 企业微信 ==========

    /** 认证订阅 */
    public const SUBSCRIBE = 'aibot_subscribe';

    /** 心跳 */
    public const PING = 'ping';

    /** 回复消息 */
    public const RESPONSE = 'aibot_respond_msg';

    /** 回复欢迎语 */
    public const RESPONSE_WELCOME = 'aibot_respond_welcome_msg';

    /** 更新模板卡片 */
    public const RESPONSE_UPDATE = 'aibot_respond_update_msg';

    /** 主动发送消息 */
    public const SEND_MSG = 'aibot_send_msg';

    /** 上传临时素材 — 初始化 */
    public const UPLOAD_INIT = 'aibot_upload_media_init';

    /** 上传临时素材 — 分片上传 */
    public const UPLOAD_CHUNK = 'aibot_upload_media_chunk';

    /** 上传临时素材 — 完成上传 */
    public const UPLOAD_FINISH = 'aibot_upload_media_finish';

    // ========== 企业微信 → 开发者 ==========

    /** 消息推送回调 */
    public const CALLBACK = 'aibot_msg_callback';

    /** 事件推送回调 */
    public const EVENT_CALLBACK = 'aibot_event_callback';

    private function __construct()
    {
    }
}
