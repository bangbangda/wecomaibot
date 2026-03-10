# WeComAiBot — 企业微信 AI 机器人 PHP SDK

## 项目概述

基于 Workerman 的企业微信 AI 机器人 WebSocket SDK。通过 WebSocket 长连接与企微服务器通信，免去公网回调地址配置，降低接入门槛。

- **包名**: `bangbangda/wecomaibot`
- **命名空间**: `WeComAiBot`
- **PHP**: >= 8.1
- **核心依赖**: `workerman/workerman: ^5.0`
- **协议**: MIT
- **仓库**: github.com/bangbangda/wecomaibot

## 技术架构

### 定位

**只做通信层 SDK**，不涉及业务逻辑（AI 对话、会议管理等）。
- 建立/维护 WebSocket 连接（wss://openws.work.weixin.qq.com）
- 消息收发（流式回复、主动推送）
- 事件分发（enter_chat、template_card_event 等）
- 文件下载 + AES 解密（V2）

### WebSocket 协议

所有通信为 JSON 帧，格式：`{ cmd, headers: { req_id }, body }`

| 方向 | cmd | 用途 |
|------|-----|------|
| 出站 | `aibot_subscribe` | 认证（body: { bot_id, secret }） |
| 出站 | `ping` | 心跳（30s） |
| 入站 | `aibot_msg_callback` | 收到用户消息 |
| 入站 | `aibot_event_callback` | 收到事件 |
| 出站 | `aibot_respond_msg` | 回复消息（流式） |
| 出站 | `aibot_respond_welcome_msg` | 回复欢迎语 |
| 出站 | `aibot_respond_update_msg` | 更新模板卡片 |
| 出站 | `aibot_send_msg` | 主动推送消息 |

### 核心设计原则

- **WeComBot** 是唯一入口类，用户不需要了解 Workerman 内部细节
- **Message** 是只读值对象，封装收到的消息
- **Reply** 是操作对象，绑定特定消息的 req_id，提供 text/stream 方法
- **WsClient** 封装 AsyncTcpConnection，处理连接/认证/心跳/重连
- Laravel 集成通过 ServiceProvider 自动注册，Artisan 命令启动

### 参考实现

Node.js 原版 SDK 位于 `/Users/hossy/Downloads/wecom-openclaw-plugin/node_modules/@wecom/aibot-node-sdk/`
OpenClaw 插件位于 `/Users/hossy/Downloads/wecom-openclaw-plugin/dist/index.esm.js`

## 目录结构

```
src/
├── WeComBot.php                  # 主入口（封装 Workerman Worker）
├── Connection/
│   └── WsClient.php             # WebSocket 连接管理
├── Protocol/
│   ├── Command.php              # 命令常量
│   └── FrameBuilder.php         # 帧构建工具
├── Message/
│   ├── Message.php              # 消息对象（只读）
│   ├── Reply.php                # 回复操作对象
│   └── MessageParser.php        # 帧 → Message 解析
├── Event/
│   ├── EventType.php            # 事件类型枚举
│   └── Event.php                # 事件对象
├── Support/
│   ├── LoggerInterface.php       # 日志接口
│   └── ConsoleLogger.php        # 默认控制台日志实现
└── Laravel/
    ├── WeComAiBotServiceProvider.php
    ├── WeComServeCommand.php
    └── config.php
```

## 编码规范

- 遵循 PSR-12 编码风格
- 类型声明：所有参数和返回值必须有类型声明
- 中文注释，变量名用英文
- 每个公开类/方法必须有 PHPDoc
- 单元测试覆盖核心逻辑（Protocol、MessageParser）
- 不硬编码配置值，通过构造函数参数传入

## 开发计划

### 第一阶段：骨架搭建 ✅ 已完成

| # | 任务 | 状态 |
|---|------|------|
| 1.1 | 初始化项目（composer.json, 目录, LICENSE） | ✅ 完成 |
| 1.2 | Protocol 层（Command, FrameBuilder） | ✅ 完成 |
| 1.3 | Connection 层（WsClient） | ✅ 完成 |
| 1.4 | Message 层（Message, MessageParser, Reply） | ✅ 完成 |
| 1.5 | 主入口类（WeComBot） | ✅ 完成 |
| 1.6 | 基础示例（examples/basic.php, stream-reply.php） | ✅ 完成 |
| 1.7 | 单元测试（31 tests, 112 assertions, 全部通过） | ✅ 完成 |

### 第二阶段：功能完善 ✅ 已完成（V1 范围）

| # | 任务 | 状态 |
|---|------|------|
| 2.1 | 流式回复（Reply::stream） | ✅ 完成 |
| 2.2 | 主动推送（WeComBot::sendMessage） | ✅ 完成 |
| 2.3 | 完整消息类型（语音/图片/文件/混排/引用） | ✅ 完成 |
| 2.4 | 事件系统（enter_chat 等） | ✅ 完成 |
| 2.5 | 日志系统（LoggerInterface + ConsoleLogger） | ✅ 完成 |

### 第三阶段：Laravel 集成 ✅ 已完成

| # | 任务 | 状态 |
|---|------|------|
| 3.1 | ServiceProvider（自动发现） | ✅ 完成 |
| 3.2 | 配置文件（config/wecomaibot.php） | ✅ 完成 |
| 3.3 | Artisan 命令（wecom:serve） | ✅ 完成 |
| 3.4 | Handler 机制（配置类名自动注册） | ✅ 完成 |

### 第四阶段：文档 + 测试 ✅ 已完成

| # | 任务 | 状态 |
|---|------|------|
| 4.1 | 单元测试（31 tests, 112 assertions） | ✅ 完成 |
| 4.2 | README 文档 | ✅ 完成 |
| 4.3 | 使用示例（basic.php, stream-reply.php） | ✅ 完成 |

### 下一步：实际连接测试

| # | 任务 | 状态 |
|---|------|------|
| 5.1 | 使用真实 bot_id/secret 连接企微测试 | 待开始 |
| 5.2 | 验证消息收发、流式回复 | 待开始 |
| 5.3 | 初始化 Git 仓库并推送 | 待开始 |

### 未来版本（V2）

| # | 功能 | 优先级 |
|---|------|--------|
| V2.1 | 模板卡片消息（replyTemplateCard） | 中 |
| V2.2 | 流式 + 卡片组合回复（replyStreamWithCard） | 中 |
| V2.3 | 更新模板卡片（updateTemplateCard） | 中 |
| V2.4 | 文件下载 + AES-256-CBC 解密 | 中 |
| V2.5 | 群组策略（groupPolicy / allowlist） | 低 |
| V2.6 | DM 策略（dmPolicy / pairing） | 低 |
| V2.7 | 多机器人实例管理 | 低 |
| V2.8 | 消息去重（防重复处理） | 低 |
| V2.9 | 回复消息回执等待（串行队列） | 中 |
| V2.10 | Markdown 文本分块（长消息拆分） | 低 |
