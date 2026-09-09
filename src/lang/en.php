<?php
/**
 * English string table.
 *
 * This file only aggregates: it merges the per-module files under
 * src/lang/en/ into a single key => string array. Edit the module
 * files, not this one. Key convention: <module>.<context>.<purpose>,
 * e.g. nav.customers, customer.list.title.
 */

$dir = __DIR__ . '/en';
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
