#!/usr/bin/env bash
# 可重跑的容器內 smoke test。只使用 bash、curl 與容器既有的 PHP/SQLite。
set -uo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
BASE_URL="${BASE_URL:-http://127.0.0.1}"
APACHE_ERROR_LOG="${APACHE_ERROR_LOG:-/tmp/eoms-apache-error.log}"
TMP_DIR="$(mktemp -d)"
COOKIE_JAR="$TMP_DIR/session.cookie"
BAD_COOKIE_JAR="$TMP_DIR/bad-session.cookie"
INJECTION="test'); DROP TABLE admin;--"
FAILURES=0

delete_injection_rows() {
    php -r '
        require "/var/www/html/config.inc.php";
        try {
            $stmt = $pdo->prepare("DELETE FROM admin WHERE fcname = :name");
            $stmt->execute([":name" => $argv[1]]);
        } catch (Throwable $ignored) {
            // 若 injection 檢查發現 admin 已遭破壞，保留原始錯誤給檢查本身處理。
        }
    ' "$INJECTION" >/dev/null 2>&1 || true
}

cleanup() {
    # 第 10 項會暫時新增一筆資料；不論中途成功或失敗都移除它。
    delete_injection_rows
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

result() {
    local status="$1" number="$2" detail="$3"
    printf '%s %s %s\n' "$status" "$number" "$detail"
    if [[ "$status" == "FAIL" ]]; then
        FAILURES=$((FAILURES + 1))
    fi
}

db_scalar() {
    php -r '
        require "/var/www/html/config.inc.php";
        $value = $pdo->query($argv[1])->fetchColumn();
        if ($value === false) {
            fwrite(STDERR, "query returned no scalar\n");
            exit(2);
        }
        echo $value;
    ' "$1"
}

check_tables() {
    local count
    count="$(db_scalar "SELECT count(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")" || return 1
    [[ "$count" == "8" ]] || { CHECK_DETAIL="資料表數=$count，預期 8"; return 1; }
    CHECK_DETAIL="資料表數=8"
}

check_seed_counts() {
    local table count details=""
    declare -A expected=([User]=11 [Employee]=10 [Product]=10 [Customer]=10 [Orders]=10 [Shipment]=10 [orderandinvoice]=3 [admin]=3)
    for table in User Employee Product Customer Orders Shipment orderandinvoice admin; do
        count="$(db_scalar "SELECT count(*) FROM \"$table\"")" || return 1
        details+="$table=$count "
        [[ "$count" == "${expected[$table]}" ]] || { CHECK_DETAIL="種子筆數不符：${details% }（$table 預期 ${expected[$table]}）"; return 1; }
    done
    CHECK_DETAIL="${details% }"
}

check_foreign_keys() {
    local value
    value="$(db_scalar 'PRAGMA foreign_keys')" || return 1
    [[ "$value" == "1" ]] || { CHECK_DETAIL="PRAGMA foreign_keys=$value，預期 1"; return 1; }
    CHECK_DETAIL="PRAGMA foreign_keys=1"
}

check_fk_cascade() {
    local value before after
    value="$(php -r '
        require "/var/www/html/config.inc.php";
        $id = $pdo->query("SELECT CustomerID FROM Customer WHERE CustomerID IN (SELECT CustomerID FROM Orders) LIMIT 1")->fetchColumn();
        if ($id === false) { fwrite(STDERR, "沒有含訂單的顧客\n"); exit(2); }
        $before = $pdo->prepare("SELECT count(*) FROM Orders WHERE CustomerID = :id");
        $before->execute([":id" => $id]);
        $beforeCount = $before->fetchColumn();
        $pdo->beginTransaction();
        $delete = $pdo->prepare("DELETE FROM Customer WHERE CustomerID = :id");
        $delete->execute([":id" => $id]);
        $after = $pdo->prepare("SELECT count(*) FROM Orders WHERE CustomerID = :id");
        $after->execute([":id" => $id]);
        $afterCount = $after->fetchColumn();
        $pdo->rollBack();
        echo "$beforeCount:$afterCount";
    ')" || return 1
    before="${value%%:*}"; after="${value##*:}"
    [[ "$before" -gt 0 && "$after" == "0" ]] || { CHECK_DETAIL="顧客訂單 $value，預期 >0:0"; return 1; }
    CHECK_DETAIL="顧客訂單 $before → $after（交易已 rollback）"
}

check_timezone() {
    local value php_epoch sqlite_local_epoch sqlite_utc_epoch local_gap utc_gap
    value="$(php -r '
        require "/var/www/html/config.inc.php";
        $row = $pdo->query("SELECT datetime(\"now\", \"localtime\"), datetime(\"now\")")->fetch(PDO::FETCH_NUM);
        echo strtotime(date("Y-m-d H:i:s")) . ":" . strtotime($row[0]) . ":" . strtotime($row[1]);
    ')" || return 1
    IFS=: read -r php_epoch sqlite_local_epoch sqlite_utc_epoch <<< "$value"
    local_gap=$((php_epoch - sqlite_local_epoch)); if (( local_gap < 0 )); then local_gap=$((-local_gap)); fi
    utc_gap=$((sqlite_local_epoch - sqlite_utc_epoch))
    [[ "$local_gap" -le 2 && "$utc_gap" == "28800" ]] || { CHECK_DETAIL="PHP/SQLite local 差 ${local_gap}s，local/UTC 差 ${utc_gap}s（預期 ≤2s、28800s）"; return 1; }
    CHECK_DETAIL="PHP/SQLite local 差 ${local_gap}s；local/UTC 差 8h"
}

check_empty_sequence() {
    local value
    value="$(php -r '
        require "/var/www/html/config.inc.php";
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM orderandinvoice");
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = \"orderandinvoice\"");
        $value = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = \"orderandinvoice\"), 0) + 1")->fetchColumn();
        $pdo->rollBack();
        echo $value;
    ')" || return 1
    [[ "$value" == "1" ]] || { CHECK_DETAIL="清空 orderandinvoice 的下一號=$value，預期 1"; return 1; }
    CHECK_DETAIL="清空 orderandinvoice 的下一號=1（交易已 rollback）"
}

check_login_success() {
    local body status
    status="$(curl -sS -L -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$TMP_DIR/login-ok.html" -w '%{http_code}' \
        --data-urlencode 'admid=Admin' --data-urlencode 'admpw=123456' --data-urlencode 'btemplogin=1' \
        "$BASE_URL/login.php")" || { CHECK_DETAIL="curl 無法連線"; return 1; }
    body="$(<"$TMP_DIR/login-ok.html")"
    [[ "$status" =~ ^2[0-9]{2}$ && "$body" == *"歡迎"* && "$(grep -c 'PHPSESSID' "$COOKIE_JAR" || true)" -ge 1 ]] || { CHECK_DETAIL="HTTP $status、PHPSESSID 或「歡迎」缺失"; return 1; }
    CHECK_DETAIL="HTTP $status、取得 PHPSESSID、頁面含「歡迎」"
}

check_login_failure() {
    local body status
    status="$(curl -sS -L -c "$BAD_COOKIE_JAR" -b "$BAD_COOKIE_JAR" -o "$TMP_DIR/login-bad.html" -w '%{http_code}' \
        --data-urlencode 'admid=Admin' --data-urlencode 'admpw=wrong-password' --data-urlencode 'btemplogin=1' \
        "$BASE_URL/login.php")" || { CHECK_DETAIL="curl 無法連線"; return 1; }
    body="$(<"$TMP_DIR/login-bad.html")"
    [[ "$status" =~ ^2[0-9]{2}$ && "$body" == *"帳號或密碼"* && "$body" != *"歡迎"* ]] || { CHECK_DETAIL="HTTP $status，錯誤訊息或未登入狀態不符"; return 1; }
    CHECK_DETAIL="HTTP $status、頁面含「帳號或密碼」、不含「歡迎」"
}

check_password_hash() {
    local hash
    hash="$(db_scalar "SELECT pw FROM User WHERE id = 'Admin'")" || return 1
    [[ "$hash" == \$2y\$* && ${#hash} == 60 ]] || { CHECK_DETAIL="Admin.pw 前綴/長度不符（前綴=${hash:0:4}、長度=${#hash}）"; return 1; }
    CHECK_DETAIL="Admin.pw 為 \$2y\$、長度 60"
}

check_injection() {
    local state status response
    # 先移除上一次中斷執行留下的同一個測試 marker，避免讀到舊列。
    delete_injection_rows
    status="$(curl -sS -L -b "$COOKIE_JAR" -o "$TMP_DIR/injection.html" -w '%{http_code}' \
        --data-urlencode 'btadd=1' --data-urlencode 'fc=廠商' --data-urlencode "fcname=$INJECTION" \
        --data-urlencode 'fcaddress=smoke-test' --data-urlencode 'fcphone=0000' --data-urlencode 'fcphonem=0000' \
        --data-urlencode 'fcemail=smoke@example.test' --data-urlencode 'fcid=SMOKE-INJECTION' \
        "$BASE_URL/index.php?Act=210")" || { CHECK_DETAIL="curl 無法送出 injection 探針"; return 1; }
    state="$(php -r '
        require "/var/www/html/config.inc.php";
        $table = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = \"table\" AND name = \"admin\"")->fetchColumn();
        $stmt = $pdo->prepare("SELECT enabled || \":\" || open || \":\" || status FROM admin WHERE fcname = :name ORDER BY prikey DESC LIMIT 1");
        $stmt->execute([":name" => $argv[1]]);
        $flags = $stmt->fetchColumn();
        echo "$table|$flags";
    ' "$INJECTION")" || { CHECK_DETAIL="admin 表不存在或查詢失敗"; return 1; }
    response="$(tr '\n' ' ' < "$TMP_DIR/injection.html" | sed 's/<[^>]*>/ /g' | tr -s ' ' | cut -c 1-120)"
    [[ "$status" =~ ^2[0-9]{2}$ && "$state" == "1|1:1:1" ]] || { CHECK_DETAIL="HTTP $status、admin 或字串保存失敗（state=$state；response=$response）"; return 1; }
    CHECK_DETAIL="HTTP $status、字串原樣保存、admin 存在、enabled/open/status=1"
}

check_routes() {
    local act body status route failures=""
    local -a acts=(100 105 110 115 160 200 210 220 230 240 250 260 265 270 300 335 375 415)
    for act in "${acts[@]}"; do
        route="index.php?Act=$act"
        body="$(curl -sS -b "$COOKIE_JAR" -w $'\n%{http_code}' "$BASE_URL/$route")" || { failures+="$route:curl "; continue; }
        status="${body##*$'\n'}"
        body="${body%$'\n'*}"
        if [[ ! "$status" =~ ^[23][0-9]{2}$ ]]; then
            failures+="$route:$status "
        elif [[ "$body" == *"Fatal error"* || "$body" == *"Error fetching"* ]]; then
            failures+="$route:PHP-error "
        fi
    done
    [[ -z "$failures" ]] || { CHECK_DETAIL="路由失敗：${failures% }"; return 1; }
    CHECK_DETAIL="18 個指定 Act 皆為 2xx/3xx"
}

check_error_log() {
    local count
    [[ -r "$APACHE_ERROR_LOG" ]] || { CHECK_DETAIL="無法讀取 $APACHE_ERROR_LOG"; return 1; }
    count="$(grep -Eic 'Deprecated|Warning|Fatal' "$APACHE_ERROR_LOG" || true)"
    [[ "$count" == "0" ]] || { CHECK_DETAIL="Apache error log 有 $count 行 Deprecated/Warning/Fatal"; return 1; }
    CHECK_DETAIL="Apache error log 無 Deprecated/Warning/Fatal"
}

check_git_hygiene() {
    local tracked bad
    tracked="$(php -r '
        $index = file_get_contents("/var/www/html/.git/index");
        if (substr($index, 0, 4) !== "DIRC") { fwrite(STDERR, "非 Git index\\n"); exit(2); }
        $version = unpack("N", substr($index, 4, 4))[1];
        $entries = unpack("N", substr($index, 8, 4))[1];
        if (!in_array($version, [2, 3], true)) { fwrite(STDERR, "不支援的 Git index v$version\\n"); exit(2); }
        $position = 12;
        for ($i = 0; $i < $entries; $i++) {
            $start = $position;
            $flags = unpack("n", substr($index, $position + 60, 2))[1];
            $position += 62 + (($flags & 0x4000) ? 2 : 0);
            $end = strpos($index, "\0", $position);
            if ($end === false) { fwrite(STDERR, "Git index entry 截斷\n"); exit(2); }
            echo substr($index, $position, $end - $position), "\n";
            $position = $start + (int)(ceil(($end + 1 - $start) / 8) * 8);
        }
    ')" || { CHECK_DETAIL="無法解析 Git index（容器 image 不含 git）"; return 1; }
    bad="$(printf '%s\n' "$tracked" | grep -Ei '(^|/)([^/]*\.sqlite|sess_[^/]*|config\.inc\.php|[^/]*\.mp3)$' || true)"
    [[ -z "$bad" ]] || { CHECK_DETAIL="已追蹤違規檔：$(printf '%s' "$bad" | tr '\n' ' ')"; return 1; }
    CHECK_DETAIL="Git index 的已追蹤檔無 .sqlite、sess_*、config.inc.php、*.mp3"
}

# 將 log 範圍限定為本次 smoke 的 HTTP 請求；entrypoint 會持續鏡像 Apache stderr 到此檔。
: > "$APACHE_ERROR_LOG"

for check in \
    '1 check_tables' \
    '2 check_seed_counts' \
    '3 check_foreign_keys' \
    '4 check_fk_cascade' \
    '5 check_timezone' \
    '6 check_empty_sequence' \
    '7a check_login_success' \
    '7b check_login_failure' \
    '9 check_password_hash' \
    '10 check_injection' \
    'ROUTES check_routes' \
    '11 check_error_log' \
    '12 check_git_hygiene'; do
    number="${check%% *}"
    function_name="${check#* }"
    CHECK_DETAIL="未提供細節"
    if "$function_name"; then
        result PASS "$number" "$CHECK_DETAIL"
    else
        result FAIL "$number" "$CHECK_DETAIL"
    fi
done

if (( FAILURES > 0 )); then
    printf 'SUMMARY FAIL %d check(s) failed\n' "$FAILURES"
    exit 1
fi
printf 'SUMMARY PASS all smoke checks passed\n'
