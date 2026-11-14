<?php

return [
    'title' => '联系人管理',
    'subtitle' => '集中管理 WhatsApp 客户联系人数据库与分组。',
    'add_contact' => '添加联系人',
    'edit_contact' => '编辑联系人',
    'delete_contact' => '删除联系人',
    'batch_delete' => '批量删除所选',
    'import_contacts' => '导入联系人',
    'export_contacts' => '导出联系人',

    // 字段
    'name' => '联系人姓名',
    'phone_number' => 'WhatsApp 号码',
    'raw_phone' => '原始输入号码',
    'group' => '所属分组',
    'select_group' => '选择联系人分组',
    'all_groups' => '全部分组',
    'status' => '联系人状态',
    'status_active' => '正常活跃',
    'status_invalid' => '无效号码',
    'status_unsubscribed' => '已取消订阅',

    // E.164 标准化
    'normalization_notice' => '电话号码将自动标准化为 E.164 国际标准格式（例如 0812... 转换为 62812...）。',
    'invalid_phone_format' => 'WhatsApp 电话号码格式不正确。',

    // 联系人分组
    'groups_title' => '联系人分组',
    'create_group' => '创建新分组',
    'group_name' => '分组名称',
    'group_slug' => '分组标识 (Slug)',
    'total_members' => '群组成员数',

    // 导入与导出
    'import_modal_title' => '批量导入联系人数据',
    'import_file_label' => '选择文件 (支持 Excel .xlsx, .csv 或 .txt)',
    'import_template_download' => '下载标准导入模版文件',
    'import_instructions' => '请确保第一列为姓名，第二列为电话号码。',
    'import_processing' => '正在分批处理联系人导入中...',
    'import_success' => '成功导入 :count 个新联系人。',
    'import_duplicates' => '已跳过 :count 个重复号码。',
    'export_all' => '导出所有联系人',

    // 提示信息
    'created_success' => '联系人添加成功。',
    'updated_success' => '联系人信息已更新。',
    'deleted_success' => '联系人已成功删除。',
    'confirm_delete' => '确定要删除此联系人吗？',
    'no_contacts' => '数据库中暂无联系人数据。',
];
