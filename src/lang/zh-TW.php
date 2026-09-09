<?php
/**
 * 繁體中文（台灣）字串表 —— 預設語言，內容最完整。
 *
 * 本檔只做「聚合」：把 src/lang/zh-TW/ 下的各模組檔合併成單一
 * key => 字串 陣列回傳。要改字串請改對應的模組檔，不要改這裡。
 * 鍵名慣例：<模組>.<情境>.<用途>，例如 nav.customers、customer.list.title。
 */

$dir = __DIR__ . '/zh-TW';
$modules = [
    'html',
    'common',
    'nav',
    'auth',
    'home',
    'profile',
    'customer',
    'employee',
    'product',
    'order',
    'shipment',
    'invoice',
    'firm',
    'user',
];

$strings = [];
foreach ($modules as $module) {
    $file = $dir . '/' . $module . '.php';
    if (is_file($file)) {
        $part = require $file;
        if (is_array($part)) {
            $strings = array_merge($strings, $part);
        }
    }
}

return $strings;
