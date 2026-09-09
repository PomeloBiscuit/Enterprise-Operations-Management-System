<?php
/**
 * 貨物模組（src/pages/product/*.php，Act=390 / 400 / 410 / 415 / 420）。
 */
return [
    'product.list.title' => '貨物列表',
    'product.add.title'  => '新增貨物',
    'product.edit.title' => '編輯貨物',

    'product.field.id'         => '貨物編號',
    'product.field.name'       => '貨物名稱',
    'product.field.category'   => '貨物類別',
    'product.field.unit_price' => '單價',

    'product.add.name_ph'         => '請輸入貨物名稱',
    'product.add.category_ph'     => '請輸入貨物類別',
    'product.add.price_ph'        => '請輸入單價',
    'product.add.id_error_prefix' => '無法取得貨物編號：',

    'product.del.fk_blocked' => '無法刪除：仍有訂單引用此產品。請先移除相關訂單的該項明細。',

    'product.none_selected'  => '未選擇任何貨物！',
    'product.confirm_delete' => '確定要刪除選中的貨物嗎？',
];
