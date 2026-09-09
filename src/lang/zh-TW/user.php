<?php
/**
 * 使用者（人員）模組（src/pages/user/*.php，Act=110 / 120 / 130 / 135 / 140）。
 * 搜尋框輸入的「是」「否」是邏輯值（見 adminList.php），不是介面文字，不在本檔。
 */
return [
    'user.list.title'      => '使用者列表',
    'user.list.add_button' => '新增人員',
    'user.add.title'       => '新增使用者',
    'user.edit.title'      => '修改使用者',

    'user.field.landline' => '固定電話',
    'user.field.mobile'   => '行動電話',
    'user.field.email'    => '電子郵件',
    'user.field.is_admin' => '管理員身份',

    'user.value.yes' => '是',
    'user.value.no'  => '否',

    'user.action.edit' => '修改',

    // 新增使用者頁
    'user.add.confirm_password'     => '確認密碼',
    'user.add.confirm_submit'       => '確認資料無誤？',
    'user.add.err_incomplete'       => '資料未填完整或密碼不一致，請重新檢查！',
    'user.add.err_prefix'           => '新增失敗：',
    'user.add.userid_error_prefix'  => '無法取得UserID：',
    'user.add.area_ph'              => '區碼',
    'user.add.area_hint'            => '請輸入有效的區碼，2到4個數字',
    'user.add.phone4_hint'         => '請輸入有效的電話號碼，格式為 4個數字',
    'user.add.mobile_hint'          => '請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx',
    'user.add.landline_format'      => '(格式: (區碼) 1234-5678)',
    'user.add.mobile_format'        => '(格式: 09xx-xxx-xxx)',

    // 修改使用者頁
    'user.edit.field_userid' => '使用者ID',
    'user.edit.loading'      => '資料更新中，請稍候...',

    // JS 對話框（多筆刪除）
    'user.none_selected'      => '未選擇任何使用者！',
    'user.confirm_delete'     => '確定要刪除選中的使用者嗎？',
    'user.confirm_self_delete' => '執行此操作會刪除此帳號，您確定要繼續執行嗎？',
];
