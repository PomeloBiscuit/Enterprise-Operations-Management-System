<?php
/**
 * 介面多國語言（i18n）機制 —— 純 PHP，無 Composer、無套件、無 gettext。
 *
 * 用法：
 *   require_once __DIR__ . '/i18n.inc.php';   // 與 config.inc.php / auth.inc.php 同一層
 *   echo t('nav.customers');                  // 取一句介面文字
 *   echo t('home.greeting', ['name' => $x]);  // 帶變數：字串裡寫 {name}
 *
 * 語言偵測優先序：網址參數 ?lang= → session → 預設 zh-TW。
 * 選定的語言寫進 $_SESSION['lang']，換頁後保持。
 *
 * ⚠ 只翻譯「介面文字」。資料庫資料（顧客姓名、產品名稱、種子資料）一律不進語言檔。
 */

const I18N_DEFAULT_LOCALE = 'zh-TW';
// 可用語言。新增語言＝在這裡加一個代碼，並補一份 src/lang/<代碼>.php。
const I18N_AVAILABLE_LOCALES = ['zh-TW', 'en'];

/**
 * 依優先序決定本次請求的語言，並把有效的 ?lang= 存進 session。
 */
function i18n_detect_locale(): string
{
    $fromQuery = $_GET['lang'] ?? null;
    if (is_string($fromQuery) && in_array($fromQuery, I18N_AVAILABLE_LOCALES, true)) {
        $_SESSION['lang'] = $fromQuery;
        return $fromQuery;
    }

    $fromSession = $_SESSION['lang'] ?? null;
    if (is_string($fromSession) && in_array($fromSession, I18N_AVAILABLE_LOCALES, true)) {
        return $fromSession;
    }

    return I18N_DEFAULT_LOCALE;
}

/**
 * 本次請求的語言代碼（zh-TW / en）。整個請求週期內固定。
 */
function current_locale(): string
{
    static $locale = null;
    if ($locale === null) {
        $locale = i18n_detect_locale();
    }
    return $locale;
}

/**
 * 載入某語言的字串表（key => 字串）。同一請求內只讀一次檔。
 */
function i18n_load(string $locale): array
{
    static $cache = [];
    if (array_key_exists($locale, $cache)) {
        return $cache[$locale];
    }

    $file = __DIR__ . '/lang/' . $locale . '.php';
    $strings = is_file($file) ? require $file : [];
    if (!is_array($strings)) {
        $strings = [];
    }

    return $cache[$locale] = $strings;
}

/**
 * 取一句介面文字。
 *
 * 缺字回退：當前語言查無 → 回退到預設語言（zh-TW，內容最完整）的值。
 * 這個回退是刻意的：漏翻的鍵在英文頁面會顯示成中文，於是英文模式的
 * CJK 殘留檢查（scripts/smoke.sh）會抓到它。若連 zh-TW 都沒有這個鍵，
 * 回傳 [[key]] 並寫一行 error_log 供開發者追。
 *
 * @param array<string,scalar> $vars  字串內 {name} 佔位符的替換值
 */
function t(string $key, array $vars = []): string
{
    $strings = i18n_load(current_locale());

    if (array_key_exists($key, $strings)) {
        $value = $strings[$key];
    } else {
        $fallback = i18n_load(I18N_DEFAULT_LOCALE);
        if (array_key_exists($key, $fallback)) {
            if (current_locale() !== I18N_DEFAULT_LOCALE) {
                error_log('[i18n] key not translated for ' . current_locale() . ': ' . $key);
            }
            $value = $fallback[$key];
        } else {
            error_log('[i18n] missing translation key: ' . $key);
            return '[[' . $key . ']]';
        }
    }

    if ($vars) {
        foreach ($vars as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }
    }

    return $value;
}

/**
 * 回到「目前這一頁」但把 lang 換成指定語言的網址，給導覽列的語言切換用。
 * 保留原本的 Act 與其他查詢參數，只覆寫 lang。
 */
function i18n_switch_url(string $locale): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? 'index.php';
    $path = strtok($uri, '?');
    if ($path === false || $path === '') {
        $path = 'index.php';
    }

    $params = $_GET;
    $params['lang'] = $locale;

    return $path . '?' . http_build_query($params);
}
