<?php
/**
 * 個人資料（src/pages/profile/*.php，Act=100 / 105 / 115）。
 */
return [
    'profile.view.title'   => '個人資料',
    'profile.edit.title'   => '修改個人資料',

    'profile.field.landline' => '固定電話',
    'profile.field.mobile'   => '行動電話',
    'profile.field.email'    => '電子郵件',
    'profile.field.is_admin' => '是否為管理員',
    'profile.field.last_modified' => '最後修改日期',
    'profile.field.created'  => '新增日期',

    'profile.value.yes' => '是',
    'profile.value.no'  => '否',

    'profile.not_found' => '找不到使用者資料。',

    // 檢視頁的刪除
    'profile.delete.confirm'   => '確定要刪除您的個人資料嗎？',
    'profile.delete.button'    => '刪除個人資料',
    'profile.delete.admin_hint' => '管理員無法刪除帳號',
    'profile.delete.admin_only_msg' => '管理員帳號無法刪除。',

    // 修改頁的電話欄位
    'profile.edit.landline_format' => '(格式: (區碼) 123-4567)',
    'profile.edit.mobile_format'   => '(格式: 09xx-xxx-xxx)',
    'profile.edit.area_hint'       => '請輸入有效的區碼，2到4個數字',
    'profile.edit.phone3_hint'     => '請輸入有效的電話號碼，格式為 3個數字',
    'profile.edit.phone4_hint'     => '請輸入有效的電話號碼，格式為 4個數字',
    'profile.edit.mobile_hint'     => '請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx',
    'profile.edit.submit'          => '更新',
];
