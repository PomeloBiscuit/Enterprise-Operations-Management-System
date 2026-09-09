<?php
/**
 * 發票模組（src/pages/invoice/*.php，Act=240 / 250 / 260 / 265 / 270）。
 */
return [
    'invoice.list.title' => '訂單與發票清單',
    'invoice.list.add'   => '新增訂單',
    'invoice.add.title'  => '新增訂單與發票',
    'invoice.edit.title' => '編輯訂單與發票',

    'invoice.field.order_id'               => '訂單號碼',
    'invoice.field.order_number_snapshot'  => '訂單編號快照',
    'invoice.field.invoice_number'         => '發票號碼',
    'invoice.field.customer_name_snapshot' => '客戶名稱快照',
    'invoice.field.amount'                 => '金額',
    'invoice.field.status'                 => '狀態',

    'invoice.status.done'    => '完成',
    'invoice.status.pending' => '未完成',

    'invoice.add.order_label'       => '訂單號碼 (OrderID)',
    'invoice.add.customer_label'    => '客戶ID',
    'invoice.add.invoice_number_ph' => '請輸入發票號碼',
    'invoice.add.amount_ph'         => '請輸入金額',
    'invoice.add.fail_prefix'       => '新增失敗：',
    'invoice.add.err_not_found'     => '所選訂單或客戶不存在。',

    'invoice.edit.order_link'    => '訂單關聯',
    'invoice.edit.customer_link' => '客戶關聯',
    'invoice.edit.not_found'     => '找不到指定的訂單與發票資料。',

    'invoice.none_selected'  => '未選擇任何訂單！',
    'invoice.confirm_delete' => '確定要刪除選中的訂單嗎？',
];
