<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Protocol\Command;
use WeComAiBot\Protocol\FrameBuilder;
use WeComAiBot\Tests\TestCase;

class FrameBuilderTest extends TestCase
{
    public function test_generate_req_id_format(): void
    {
        $reqId = FrameBuilder::generateReqId('test');

        $this->assertStringStartsWith('test_', $reqId);
        // 格式：test_{timestamp}_{random_8hex}
        $this->assertMatchesRegularExpression('/^test_\d+_[0-9a-f]{8}$/', $reqId);
    }

    public function test_generate_req_id_uniqueness(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = FrameBuilder::generateReqId('test');
        }

        $this->assertCount(100, array_unique($ids), 'generateReqId should produce unique IDs');
    }

    public function test_auth_frame(): void
    {
        $frame = json_decode(FrameBuilder::auth('bot123', 'secret456'), true);

        $this->assertSame(Command::SUBSCRIBE, $frame['cmd']);
        $this->assertArrayHasKey('req_id', $frame['headers']);
        $this->assertStringStartsWith(Command::SUBSCRIBE . '_', $frame['headers']['req_id']);
        $this->assertSame('bot123', $frame['body']['bot_id']);
        $this->assertSame('secret456', $frame['body']['secret']);
    }

    public function test_ping_frame(): void
    {
        $frame = json_decode(FrameBuilder::ping(), true);

        $this->assertSame(Command::PING, $frame['cmd']);
        $this->assertArrayHasKey('req_id', $frame['headers']);
        $this->assertStringStartsWith(Command::PING . '_', $frame['headers']['req_id']);
        $this->assertArrayNotHasKey('body', $frame);
    }

    public function test_reply_stream_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::replyStream('req_123', 'stream_456', '你好世界', false),
            true,
        );

        $this->assertSame(Command::RESPONSE, $frame['cmd']);
        $this->assertSame('req_123', $frame['headers']['req_id']);
        $this->assertSame('stream', $frame['body']['msgtype']);
        $this->assertSame('stream_456', $frame['body']['stream']['id']);
        $this->assertSame('你好世界', $frame['body']['stream']['content']);
        $this->assertFalse($frame['body']['stream']['finish']);
    }

    public function test_reply_stream_frame_finish(): void
    {
        $frame = json_decode(
            FrameBuilder::replyStream('req_123', 'stream_456', '最终回复', true),
            true,
        );

        $this->assertTrue($frame['body']['stream']['finish']);
        $this->assertSame('最终回复', $frame['body']['stream']['content']);
    }

    public function test_send_message_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::sendMessage('user001', '**加粗文本**'),
            true,
        );

        $this->assertSame(Command::SEND_MSG, $frame['cmd']);
        $this->assertStringStartsWith(Command::SEND_MSG . '_', $frame['headers']['req_id']);
        $this->assertSame('user001', $frame['body']['chatid']);
        $this->assertSame(0, $frame['body']['chat_type']);
        $this->assertSame('markdown', $frame['body']['msgtype']);
        $this->assertSame('**加粗文本**', $frame['body']['markdown']['content']);
    }

    public function test_send_message_single_chat(): void
    {
        $frame = json_decode(
            FrameBuilder::sendMessage('zhangsan', '你好', 1),
            true,
        );

        $this->assertSame(1, $frame['body']['chat_type']);
        $this->assertSame('zhangsan', $frame['body']['chatid']);
    }

    public function test_send_message_group_chat(): void
    {
        $frame = json_decode(
            FrameBuilder::sendMessage('group123', '通知', 2),
            true,
        );

        $this->assertSame(2, $frame['body']['chat_type']);
        $this->assertSame('group123', $frame['body']['chatid']);
    }

    public function test_reply_welcome_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::replyWelcome('req_event_123', '欢迎！'),
            true,
        );

        $this->assertSame(Command::RESPONSE_WELCOME, $frame['cmd']);
        $this->assertSame('req_event_123', $frame['headers']['req_id']);
        $this->assertSame('text', $frame['body']['msgtype']);
        $this->assertSame('欢迎！', $frame['body']['text']['content']);
    }

    public function test_send_template_card_frame(): void
    {
        $card = [
            'card_type' => 'button_interaction',
            'main_title' => ['title' => '告警', 'desc' => 'CPU 超过 90%'],
            'button_list' => [
                ['text' => '确认', 'style' => 1, 'key' => 'confirm'],
                ['text' => '误报', 'style' => 2, 'key' => 'false_alarm'],
            ],
            'task_id' => 'TASK_001',
        ];

        $frame = json_decode(
            FrameBuilder::sendTemplateCard('user001', $card, 1),
            true,
        );

        $this->assertSame(Command::SEND_MSG, $frame['cmd']);
        $this->assertStringStartsWith(Command::SEND_MSG . '_', $frame['headers']['req_id']);
        $this->assertSame('user001', $frame['body']['chatid']);
        $this->assertSame(1, $frame['body']['chat_type']);
        $this->assertSame('template_card', $frame['body']['msgtype']);
        $this->assertSame($card, $frame['body']['template_card']);
    }

    public function test_send_template_card_group(): void
    {
        $card = ['card_type' => 'button_interaction', 'task_id' => 'T1'];

        $frame = json_decode(
            FrameBuilder::sendTemplateCard('group123', $card, 2),
            true,
        );

        $this->assertSame(2, $frame['body']['chat_type']);
        $this->assertSame('group123', $frame['body']['chatid']);
        $this->assertSame('template_card', $frame['body']['msgtype']);
    }

    public function test_send_template_card_default_chat_type(): void
    {
        $card = ['card_type' => 'button_interaction', 'task_id' => 'T1'];

        $frame = json_decode(
            FrameBuilder::sendTemplateCard('someone', $card),
            true,
        );

        $this->assertSame(0, $frame['body']['chat_type']);
    }

    public function test_update_template_card_frame(): void
    {
        $card = [
            'card_type' => 'button_interaction',
            'main_title' => ['title' => '告警', 'desc' => '已处理'],
            'button_list' => [
                ['text' => '已确认', 'style' => 1, 'key' => 'confirm'],
            ],
            'task_id' => 'TASK_001',
            'feedback' => ['id' => 'FB_001'],
        ];

        $frame = json_decode(
            FrameBuilder::updateTemplateCard('req_event_456', $card),
            true,
        );

        $this->assertSame(Command::RESPONSE_UPDATE, $frame['cmd']);
        $this->assertSame('req_event_456', $frame['headers']['req_id']);
        $this->assertSame('update_template_card', $frame['body']['response_type']);
        $this->assertSame($card, $frame['body']['template_card']);
    }

    public function test_update_template_card_preserves_structure(): void
    {
        // 确保复杂嵌套结构完整透传
        $card = [
            'card_type' => 'button_interaction',
            'main_title' => ['title' => '审批', 'desc' => '请处理'],
            'button_list' => [
                ['text' => '同意', 'style' => 1, 'key' => 'approve'],
                ['text' => '拒绝', 'style' => 2, 'key' => 'reject'],
                ['text' => '转交', 'style' => 3, 'key' => 'transfer'],
            ],
            'task_id' => 'APPROVAL_001',
        ];

        $frame = json_decode(
            FrameBuilder::updateTemplateCard('req_789', $card),
            true,
        );

        $this->assertCount(3, $frame['body']['template_card']['button_list']);
        $this->assertSame('approve', $frame['body']['template_card']['button_list'][0]['key']);
    }

    // ========== 媒体消息帧 ==========

    public function test_send_image_frame(): void
    {
        $frame = json_decode(FrameBuilder::sendImage('user001', 'MEDIA_IMG_001', 1), true);

        $this->assertSame(Command::SEND_MSG, $frame['cmd']);
        $this->assertSame('user001', $frame['body']['chatid']);
        $this->assertSame(1, $frame['body']['chat_type']);
        $this->assertSame('image', $frame['body']['msgtype']);
        $this->assertSame('MEDIA_IMG_001', $frame['body']['image']['media_id']);
    }

    public function test_send_file_frame(): void
    {
        $frame = json_decode(FrameBuilder::sendFile('user001', 'MEDIA_FILE_001', 1), true);

        $this->assertSame('file', $frame['body']['msgtype']);
        $this->assertSame('MEDIA_FILE_001', $frame['body']['file']['media_id']);
    }

    public function test_send_voice_frame(): void
    {
        $frame = json_decode(FrameBuilder::sendVoice('user001', 'MEDIA_VOICE_001', 1), true);

        $this->assertSame('voice', $frame['body']['msgtype']);
        $this->assertSame('MEDIA_VOICE_001', $frame['body']['voice']['media_id']);
    }

    public function test_send_video_frame(): void
    {
        $frame = json_decode(FrameBuilder::sendVideo('user001', 'MEDIA_VIDEO_001', 1, '标题', '描述'), true);

        $this->assertSame('video', $frame['body']['msgtype']);
        $this->assertSame('MEDIA_VIDEO_001', $frame['body']['video']['media_id']);
        $this->assertSame('标题', $frame['body']['video']['title']);
        $this->assertSame('描述', $frame['body']['video']['description']);
    }

    public function test_send_video_frame_without_title(): void
    {
        $frame = json_decode(FrameBuilder::sendVideo('user001', 'MEDIA_VIDEO_001'), true);

        $this->assertSame('video', $frame['body']['msgtype']);
        $this->assertSame('MEDIA_VIDEO_001', $frame['body']['video']['media_id']);
        $this->assertArrayNotHasKey('title', $frame['body']['video']);
        $this->assertArrayNotHasKey('description', $frame['body']['video']);
    }

    // ========== 上传临时素材帧 ==========

    public function test_upload_media_init_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::uploadMediaInit('voice', 'audio.amr', 10240, 1, 'abc123md5'),
            true,
        );

        $this->assertSame(Command::UPLOAD_INIT, $frame['cmd']);
        $this->assertStringStartsWith(Command::UPLOAD_INIT . '_', $frame['headers']['req_id']);
        $this->assertSame('voice', $frame['body']['type']);
        $this->assertSame('audio.amr', $frame['body']['filename']);
        $this->assertSame(10240, $frame['body']['total_size']);
        $this->assertSame(1, $frame['body']['total_chunks']);
        $this->assertSame('abc123md5', $frame['body']['md5']);
    }

    public function test_upload_media_init_without_md5(): void
    {
        $frame = json_decode(
            FrameBuilder::uploadMediaInit('image', 'photo.jpg', 5000, 1),
            true,
        );

        $this->assertArrayNotHasKey('md5', $frame['body']);
    }

    public function test_upload_media_chunk_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::uploadMediaChunk('UPLOAD_001', 0, 'base64data=='),
            true,
        );

        $this->assertSame(Command::UPLOAD_CHUNK, $frame['cmd']);
        $this->assertStringStartsWith(Command::UPLOAD_CHUNK . '_', $frame['headers']['req_id']);
        $this->assertSame('UPLOAD_001', $frame['body']['upload_id']);
        $this->assertSame(0, $frame['body']['chunk_index']);
        $this->assertSame('base64data==', $frame['body']['base64_data']);
    }

    public function test_upload_media_finish_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::uploadMediaFinish('UPLOAD_001'),
            true,
        );

        $this->assertSame(Command::UPLOAD_FINISH, $frame['cmd']);
        $this->assertStringStartsWith(Command::UPLOAD_FINISH . '_', $frame['headers']['req_id']);
        $this->assertSame('UPLOAD_001', $frame['body']['upload_id']);
    }

    public function test_chinese_content_not_escaped(): void
    {
        $json = FrameBuilder::sendMessage('user1', '你好');

        // JSON 中中文不应被转义为 \uXXXX
        $this->assertStringContainsString('你好', $json);
        $this->assertStringNotContainsString('\u', $json);
    }
}
