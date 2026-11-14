<?php

return [
    'title' => '联系人管理',
    'subtitle' => '管理客户 WhatsApp 手机号数据库与目标分组。',
    'add_contact' => '添加联系人',
    'edit_contact' => '编辑联系人',
    'delete_contact' => '删除联系人',
    'batch_delete' => '批量删除选中',
    'import_contacts' => '导入联系人',
    'export_contacts' => '导出联系人',

    // 字段
    'name' => '联系人姓名',
    'phone_number' => 'WhatsApp 号码',
    'raw_phone' => '原始输入号码',
    'group' => '所属分组',
    'select_group' => '选择联系人分组',
    'all_groups' => '所有分组',
    'all_status' => '所有状态',
    'status' => '联系人状态',
    'status_active' => '有效活跃',
    'status_invalid' => '无效格式',
    'status_unsubscribed' => '已退订',
    'search_placeholder' => '搜索姓名或 WhatsApp 手机号...',

    // 统计摘要
    'stat_total' => '联系人总数',
    'stat_valid' => '有效E.164联系人（活跃）',
    'stat_invalid' => '无效号码',
    'stat_unsubscribed' => '已退订',

    // E.164 规范化
    'normalization_notice' => '系统将自动规范化为国际 E.164 格式（例如 0812... 转为 62812...）。',
    'invalid_phone_format' => '无效的 WhatsApp 手机号格式。',

    // 联系人分组
    'groups_title' => '联系人分组',
    'create_group' => '创建新分组',
    'back_to_contacts' => '返回联系人列表',
    'save_group' => '保存分组',
    'group_name' => '分组名称',
    'group_name_placeholder' => '例如：VIP 核心客户、网络研讨会潜在用户',
    'group_slug' => '分组标识别名',
    'total_members' => '成员数量',
    'members_count' => ':count 位成员',
    'no_group_assigned' => '未分配分组',
    'assign_to_group' => '分配至分组 (可选)',
    'confirm_delete_group' => '确定要删除此分组吗？分组内的联系人不会被删除。',
    'no_groups' => '尚未创建任何联系人分组。请在上方创建新分组。',

    // 导入与导出
    'import_modal_title' => '导入联系人数据库',
    'import_file_label' => '选择文件 (Excel .xlsx, .csv 或 .txt)',
    'import_template_download' => '下载示例导入模版',
    'import_instructions' => '请确保第一列为姓名，第二列为包含国家区号的 WhatsApp 手机号码。',
    'import_processing' => '正在导入并校验联系人...',
    'import_success' => '成功导入 :count 个新联系人。',
    'import_duplicates' => '已跳过 :count 个重复联系人。',
    'export_all' => '导出全部联系人',
    'start_import' => '开始导入',
    'update_btn' => '更新保存',

    // 提示
    'created_success' => '新联系人已成功保存。',
    'updated_success' => '联系人资料已成功更新。',
    'deleted_success' => '联系人已成功删除。',
    'confirm_delete' => '确定要永久删除此联系人吗？',
    'confirm_batch_delete' => '确定要批量删除所选中的联系人吗？',
    'no_contacts' => '数据库中尚无任何联系人记录。',
];
