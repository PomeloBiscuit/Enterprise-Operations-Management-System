<?php
/**
 * 訂單模組（src/pages/order/*.php，Act=430 / 440 / 450 / 455 / 460）。
 */
return [
    'order.list.title' => '訂單列表',
    'order.add.title'  => '新增訂單',
    'order.edit.title' => '編輯訂單',

    'order.field.item_count'  => '品項數',
    'order.field.order_total' => '訂單金額',
    'order.field.line_items'  => '訂單明細（產品與數量）',
    'order.field.product'     => '產品',
    'order.field.quantity'    => '數量',

    'order.form.select_employee' => '選擇員工',
    'order.form.select_customer' => '選擇顧客',
    'order.form.select_product'  => '選擇產品',

    'order.add.add_row'            => '＋ 新增一列',
    'order.add.employee_search_ph' => '輸入 Employee ID 或姓名',
    'order.add.customer_search_ph' => '搜尋 Customer ID、姓名或電話',
    'order.add.err_qty'            => '產品 {pid} 的數量必須大於 0',
    'order.add.err_no_items'       => '訂單至少要有一項產品',

    'order.edit.employee_search_ph'    => '搜尋 Employee ID 或姓名',
    'order.edit.customer_search_ph'    => '搜尋 Customer ID 或姓名',
    'order.edit.ship_method_search_ph' => '搜尋 Ship Method（Air、Sea、Land）',

    'order.js.keep_one_row'    => '至少要保留一列明細',
    'order.js.need_valid_line' => '請至少選擇一項產品並填入大於 0 的數量',

    'order.none_selected'  => '未選擇任何訂單！',
    'order.confirm_delete' => '確定要刪除選中的訂單嗎？',
];
