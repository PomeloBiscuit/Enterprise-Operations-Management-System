<?php
/**
 * 跨頁共用的字串：按鈕、表格表頭、搜尋列、分頁、通用訊息。
 */
return [
    // 權限 / 狀態
    'common.permission_denied' => '權限不足!',
    'common.no_data'           => '查無資料',
    'common.error_prefix'      => '錯誤：',
    'common.db_error_prefix'   => '資料庫錯誤：',
    'common.delete_fail_prefix' => '刪除失敗：',
    'common.update'            => '更新',
    'common.select_area'       => '選擇地區',
    'common.invalid_id'       => '無效的 ID！',

    // 通用按鈕 / 動作
    'common.action'        => '功能',
    'common.edit'          => '編輯',
    'common.delete'        => '刪除',
    'common.add'           => '新增',
    'common.search'        => '搜尋',
    'common.back'          => '返回',
    'common.clear'         => '清除',
    'common.submit'        => '送出',
    'common.confirm'       => '確定',
    'common.cancel'        => '取消',
    'common.save'          => '儲存',
    'common.select_all'    => '全選',
    'common.delete_selected' => '刪除勾選的資料',
    'common.show_all'      => '顯示所有資料',

    // 搜尋列
    'common.search.per_page'      => '顯示筆數',
    'common.search.pick_column'   => '選擇搜尋條件',
    'common.search.all_columns'   => '全部',
    'common.search.placeholder'   => '輸入搜尋內容',

    // 分頁
    'common.page.prev' => '上一頁',
    'common.page.next' => '下一頁',

    // 刪除確認（多筆勾選）
    'common.confirm.none_selected' => '尚未勾選任何資料！',
    'common.confirm.delete_selected' => '確定要刪除勾選的資料嗎？',

    // 通用欄位標籤（多個模組共用）
    'field.name'      => '姓名',
    'field.account'   => '帳號',
    'field.password'  => '密碼',
    'field.email'     => 'Email',
    'field.phone'     => '電話',
    'field.mobile'    => '手機',
    'field.address'   => '地址',
    'field.party_type' => '身分',

    // party_type 顯示文字（value 仍存中文，見各 Add 頁）
    'party.firm'     => '廠商',
    'party.customer' => '客戶',
];
