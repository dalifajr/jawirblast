<?php

return [
    'title' => '消息投递跟踪',
    'subtitle' => '通过服务端推送 (SSE) 实现毫秒级消息勾选状态实时监控。',

    // 状态与勾选标记
    'status_pending' => '排队等待中',
    'status_sent' => '已发送 (单勾)',
    'status_delivered' => '已送达 (双灰勾)',
    'status_read' => '已阅读 (双蓝勾)',
    'status_failed' => '发送失败',

    'badge_sent_desc' => '消息已成功送出至 WhatsApp 官方服务器。',
    'badge_delivered_desc' => '消息已成功送达对方手机设备。',
    'badge_read_desc' => '对方已打开并阅读了此消息。',
    'badge_failed_desc' => '因号码无效或网络原因发送失败。',

    // 列表字段
    'col_recipient' => '接收人姓名',
    'col_phone' => '目标号码',
    'col_message' => '消息内容',
    'col_status' => '送达状态',
    'col_sent_at' => '发送时间',
    'col_delivered_at' => '送达时间',
    'col_read_at' => '阅读时间',
    'col_error' => '失败原因',

    // 实时同步
    'sse_connected' => 'SSE 实时推送流已建立',
    'sse_reconnecting' => '正在重新连接数据流...',
    'resend_failed_action' => '重发失败消息',
    'resend_success' => '失败消息已重新进入发送队列。',
];
