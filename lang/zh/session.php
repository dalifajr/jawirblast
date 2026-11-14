<?php

return [
    'title' => 'WhatsApp 会话管理',
    'subtitle' => '基于官方 Baileys 多设备协议关联并管理 WhatsApp 发信账号。',
    'status_card' => '当前连接状态',
    'status_connected' => '已连接 (在线)',
    'status_disconnected' => '未连接 (离线)',
    'status_connecting' => '正在连接服务器...',
    'status_qr_ready' => '二维码已就绪，等待扫码',
    'connected_jid' => '发信 JID: :jid',
    'connected_phone' => '当前已绑定号码: :phone',
    'last_active' => '最后活跃时间: :time',

    // 绑定方式
    'link_device_title' => '关联 WhatsApp 设备',
    'tab_qr_code' => '扫码关联 (二维码)',
    'tab_pairing_code' => '8 位配对码关联 (手机免相机)',

    // 扫码步骤
    'qr_step_1' => '在手机上打开 WhatsApp 应用程序。',
    'qr_step_2' => '点击右上角菜单或设置 > 已关联的设备。',
    'qr_step_3' => '点击“关联设备”并将手机摄像头对准下方的二维码。',
    'qr_refresh_button' => '刷新二维码',
    'qr_expired' => '二维码已失效，请重新刷新。',

    // 配对码
    'pairing_phone_label' => '您的 WhatsApp 电话号码',
    'pairing_phone_placeholder' => '6281234567890',
    'pairing_phone_help' => '请输入包含国际区号的完整号码（无需 + 号）。',
    'pairing_request_btn' => '获取 8 位配对码',
    'pairing_code_display' => '您的配对码：',
    'pairing_step_info' => '在手机 WhatsApp 弹出的配对通知中输入上方 8 位配对码即可。',

    // 断开连接
    'disconnect_btn' => '断开 WhatsApp 会话',
    'disconnect_confirm' => '确定要断开此 WhatsApp 设备的连接吗？',
    'disconnect_success' => 'WhatsApp 会话已成功断开。',
];
