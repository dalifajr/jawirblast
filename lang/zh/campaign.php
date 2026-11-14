<?php

return [
    'title' => '群发营销活动',
    'subtitle' => '具备防封控间歇延迟队列的 WhatsApp 批量消息群发系统。',
    'create_campaign' => '创建新群发活动',
    'campaign_detail' => '活动详情',
    'campaign_title' => '活动标题',
    'campaign_title_placeholder' => '例如：周末特惠限时促销',

    // 目标受众
    'target_recipients' => '接收目标群',
    'all_contacts' => '数据库全量联系人 (:count 人)',
    'filter_by_group' => '按联系人分组筛选',
    'by_group' => '按联系人分组',
    'by_contacts' => '选择特定联系人',
    'total_selected' => '已选定 :count 个目标号码',

    // 消息模版与 Spintax
    'message_template' => '群发消息模版',
    'message_placeholder' => '在此撰写您的营销内容...',
    'variables_hint' => '支持动态变量：{name} 表示联系人姓名，{phone} 表示电话号码。',
    'spintax_hint' => '支持 Spintax 变体随机语法：{您好|早上好|亲爱的顾客} {朋友|会员}。',
    'spintax_hint_long' => '使用花括号与管道符如 {您好|Hi|问候} 随机生成内容变体，有效规避 WhatsApp 垃圾拦截。',
    'spintax_test' => '测试 Spintax 随机生成效果',
    'media_attachment' => '媒体附件 (可选: JPG/PNG 图片)',
    'spintax_calculator' => 'Spintax 计算器',
    'estimated_variations' => '预计唯一变体数',
    'message_ready' => '消息就绪',
    'preview_random_sample' => '预览随机变体',

    // WhatsApp 会话与警告
    'wa_not_connected' => 'WhatsApp 会话尚未连接',
    'connect_wa_btn' => '连接 WhatsApp',

    // 防卡顿延迟设置
    'pacing_title' => '发送速度与防封控间隔设置 (Anti-Freeze)',
    'min_delay' => '最小间隔时间 (秒)',
    'max_delay' => '最大间隔时间 (秒)',
    'delay_help' => '系统采用底层原生延迟队列，每条消息随机产生间隔时间，有效规避封号与服务器卡死。',
    'estimated_time' => '预计完成耗时: ~:time',

    // 控制动作
    'start_broadcast' => '启动群发任务',
    'pause_broadcast' => '暂停发送',
    'resume_broadcast' => '继续发送',
    'cancel_broadcast' => '取消活动',
    'save_draft' => '保存为草稿',
    'send_now' => '立即发送广播',
    'all_status' => '所有状态',
    'filter' => '筛选',
    'search_placeholder' => '搜索活动名称...',

    // 统计摘要
    'total_campaigns' => '活动总数',
    'messages_sent' => '已发送消息',
    'messages_delivered' => '已送达消息',
    'messages_read' => '已读消息',
    'messages_failed' => '发送失败',

    // 状态
    'status_draft' => '草稿',
    'status_scheduled' => '已计划',
    'status_processing' => '正在发送',
    'status_completed' => '已完成',
    'status_paused' => '已暂停',
    'status_cancelled' => '已取消',
    'status_failed' => '发送失败',

    // 进度
    'progress_title' => '群发进度',
    'processed_count' => '已处理 :sent / :total 条消息 (:percent%)',
    'confirm_start' => '确认立即向选中的 :count 个联系人发送广播吗？',
];
