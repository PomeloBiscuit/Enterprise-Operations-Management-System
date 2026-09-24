<?php
/**
 * 將跨語言的枚舉標籤轉成資料庫代碼，不改變目前頁面的語言狀態。
 */
require_once __DIR__ . '/i18n.inc.php';

/**
 * @param array<int,string> $localeKeyMap 資料庫代碼 => i18n key
 */
function search_enum_code(string $value, array $localeKeyMap): ?int
{
    $value = trim($value);

    foreach ($localeKeyMap as $code => $key) {
        if ($value === (string) $code) {
            return $code;
        }
    }

    foreach (I18N_AVAILABLE_LOCALES as $locale) {
        $strings = i18n_load($locale);
        foreach ($localeKeyMap as $code => $key) {
            if (!array_key_exists($key, $strings)) {
                continue;
            }

            if (strcasecmp($value, trim((string) $strings[$key])) === 0) {
                return $code;
            }
        }
    }

    return null;
}
