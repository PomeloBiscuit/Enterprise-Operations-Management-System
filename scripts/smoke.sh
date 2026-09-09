#!/usr/bin/env bash
# 可重跑的容器內 smoke test。只使用 bash、curl 與容器既有的 PHP/SQLite。
set -uo pipefail

APP_DIR="${APP_DIR:-/var/www/app}"
BASE_URL="${BASE_URL:-http://127.0.0.1}"
APACHE_ERROR_LOG="${APACHE_ERROR_LOG:-/tmp/eoms-apache-error.log}"
TMP_DIR="$(mktemp -d)"
COOKIE_JAR="$TMP_DIR/session.cookie"
BAD_COOKIE_JAR="$TMP_DIR/bad-session.cookie"
EXTERNAL_COOKIE_JAR="$TMP_DIR/external-session.cookie"
INJECTION="test'); DROP TABLE admin;--"
EXTERNAL_ID="smoke-external"
EXTERNAL_PASSWORD="smoke-external-password"
FAILURES=0

# WO-10 後：DocumentRoot 是 public/。只有這幾個 public/ 入口檔該對外可達；
# 其餘 public/*.php（例如頁面檔被誤放進 docroot）必須打不到。src/ 與 scripts/
# 完全在 DocumentRoot 之外，任何檔案都應是 HTTP 404。
declare -A PUBLIC_ENTRY_EXEMPTIONS=(
    [index.php]="公開前端控制器，未登入時呈現登入頁"
    [login.php]="公開登入入口"
    [logout.php]="公開登出端點，必須可清除既有 session"
    [register.php]="公開註冊入口"
)

delete_injection_rows() {
    php -r '
        require "/var/www/app/src/config.inc.php";
        try {
            $stmt = $pdo->prepare("DELETE FROM admin WHERE fcname = :name");
            $stmt->execute([":name" => $argv[1]]);
        } catch (Throwable $ignored) {
            // 若 injection 檢查發現 admin 已遭破壞，保留原始錯誤給檢查本身處理。
        }
    ' "$INJECTION" >/dev/null 2>&1 || true
}

delete_external_user() {
    php -r '
        require "/var/www/app/src/config.inc.php";
        $stmt = $pdo->prepare("DELETE FROM User WHERE id = :id");
        $stmt->execute([":id" => $argv[1]]);
    ' "$EXTERNAL_ID" >/dev/null 2>&1 || true
}

cleanup() {
    # 第 10 項會暫時新增一筆資料；不論中途成功或失敗都移除它。
    delete_injection_rows
    delete_external_user
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
        require "/var/www/app/src/config.inc.php";
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
    [[ "$count" == "9" ]] || { CHECK_DETAIL="資料表數=$count，預期 9"; return 1; }
    CHECK_DETAIL="資料表數=9"
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
        require "/var/www/app/src/config.inc.php";
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO Customer (CustomerName, CustomerPhoneNumber, CustomerAddress) VALUES (\"smoke-fk-cascade\", \"0000\", \"smoke\")");
        $id = $pdo->lastInsertId();
        $employeeId = $pdo->query("SELECT EmployeeID FROM Employee LIMIT 1")->fetchColumn();
        if ($employeeId === false) { fwrite(STDERR, "缺少建立測試訂單所需資料\n"); exit(2); }
        $insertOrder = $pdo->prepare("INSERT INTO Orders (CustomerID, EmployeeID, TrackingNumber) VALUES (:customer_id, :employee_id, \"SMOKE-FK\")");
        $insertOrder->execute([":customer_id" => $id, ":employee_id" => $employeeId]);
        $before = $pdo->prepare("SELECT count(*) FROM Orders WHERE CustomerID = :id");
        $before->execute([":id" => $id]);
        $beforeCount = $before->fetchColumn();
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
        require "/var/www/app/src/config.inc.php";
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
        require "/var/www/app/src/config.inc.php";
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

check_invoice_snapshots() {
    local count
    count="$(db_scalar "SELECT count(*) FROM orderandinvoice WHERE order_id IS NULL OR customer_id IS NULL OR order_number IS NULL OR trim(CAST(order_number AS TEXT)) = '' OR customer_name IS NULL OR trim(customer_name) = ''")" || return 1
    [[ "$count" == "0" ]] || { CHECK_DETAIL="有 $count 筆發票缺少外鍵或快照"; return 1; }
    CHECK_DETAIL="所有發票均有 order/customer 外鍵與非空快照"
}

check_order_totals() {
    # 需要已登入的 $COOKIE_JAR（由檢查 7a 建立）才能開 OrderList。
    local oid orphan expected wrong shown
    # 每張訂單都至少要有一筆明細（遷移不能做一半）
    orphan="$(db_scalar "SELECT count(*) FROM Orders WHERE OrderID NOT IN (SELECT OrderID FROM Contain)")" || return 1
    [[ "$orphan" == "0" ]] || { CHECK_DETAIL="有 $orphan 張訂單沒有任何 Contain 明細"; return 1; }
    # 找一張品項數 >= 2 的訂單（種子資料必須涵蓋多品項情境）
    oid="$(db_scalar "SELECT OrderID FROM Contain GROUP BY OrderID HAVING count(*) >= 2 ORDER BY OrderID LIMIT 1")" || { CHECK_DETAIL="找不到品項數>=2 的訂單，種子資料未涵蓋多品項情境"; return 1; }
    # 正確算式 SUM(數量 × 單價) 與「漏乘數量」的錯誤算式
    expected="$(db_scalar "SELECT CAST(SUM(c.Quantity * p.UnitPrice) AS INT) FROM Contain c JOIN Product p ON p.ProductID = c.ProductID WHERE c.OrderID = $oid")" || return 1
    wrong="$(db_scalar "SELECT CAST(SUM(p.UnitPrice) AS INT) FROM Contain c JOIN Product p ON p.ProductID = c.ProductID WHERE c.OrderID = $oid")" || return 1
    # 這張訂單的數量若全為 1，兩個算式會相同，探針就沒有鑑別力
    [[ "$expected" != "$wrong" ]] || { CHECK_DETAIL="訂單 $oid 的數量全為 1，無法辨別漏乘數量的錯誤（expected=wrong=$expected）"; return 1; }
    # OrderList.php 走 HTTP 實際渲染出的金額（讀 <tr data-order-total>）
    curl -sS -b "$COOKIE_JAR" -o "$TMP_DIR/order-list-$oid.html" \
        "$BASE_URL/index.php?Act=430&searchColumn=OrderID&searchValue=$oid&resultsPerPage=50" || { CHECK_DETAIL="OrderList curl 失敗"; return 1; }
    shown="$(sed -nE "s/.*data-order-id='$oid' data-order-total='([0-9]+)'.*/\1/p" "$TMP_DIR/order-list-$oid.html" | head -1)"
    [[ -n "$shown" ]] || { CHECK_DETAIL="OrderList 未輸出訂單 $oid 的 data-order-total"; return 1; }
    [[ "$shown" == "$expected" ]] || { CHECK_DETAIL="訂單 $oid：畫面金額=$shown，SUM(數量×單價)=$expected（漏乘數量會得 $wrong）"; return 1; }
    CHECK_DETAIL="訂單 $oid：畫面金額=$shown = SUM(數量×單價)；漏乘數量會得 $wrong，可辨別"
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

check_named_permission_guards() {
    local entry file function_name failures="" checked=0
    # <模組>/<檔名>:<應使用的具名守衛函式>。路徑相對於 $APP_DIR。
    local -a expected_guards=(
        'src/pages/Home.php:can_access_self'
        'src/pages/customer/CustomerAdd.php:can_view_business_data'
        'src/pages/customer/CustomerDel.php:can_view_business_data'
        'src/pages/customer/CustomerDelBatch.php:can_view_business_data'
        'src/pages/customer/CustomerEdit.php:can_view_business_data'
        'src/pages/customer/CustomerList.php:can_view_business_data'
        'src/pages/employee/EmployeeAdd.php:can_view_business_data'
        'src/pages/employee/EmployeeDel.php:can_view_business_data'
        'src/pages/employee/EmployeeDelBatch.php:can_view_business_data'
        'src/pages/employee/EmployeeEdit.php:can_view_business_data'
        'src/pages/employee/EmployeeList.php:can_view_business_data'
        'src/pages/product/ProductAdd.php:can_view_business_data'
        'src/pages/product/ProductDel.php:can_view_business_data'
        'src/pages/product/ProductDelBatch.php:can_view_business_data'
        'src/pages/product/ProductEdit.php:can_view_business_data'
        'src/pages/product/ProductList.php:can_view_business_data'
        'src/pages/order/OrderAdd.php:can_view_business_data'
        'src/pages/order/OrderDel.php:can_view_business_data'
        'src/pages/order/OrderDelBatch.php:can_view_business_data'
        'src/pages/order/OrderEdit.php:can_view_business_data'
        'src/pages/order/OrderList.php:can_view_business_data'
        'src/pages/shipment/ShipmentAdd.php:can_view_business_data'
        'src/pages/shipment/ShipmentDel.php:can_view_business_data'
        'src/pages/shipment/ShipmentDelBatch.php:can_view_business_data'
        'src/pages/shipment/ShipmentEdit.php:can_view_business_data'
        'src/pages/shipment/ShipmentList.php:can_view_business_data'
        'src/pages/invoice/orderandinvoiceAdd.php:can_view_business_data'
        'src/pages/invoice/orderandinvoiceDel.php:can_view_business_data'
        'src/pages/invoice/orderandinvoiceDelBatch.php:can_view_business_data'
        'src/pages/invoice/orderandinvoiceEdit.php:can_view_business_data'
        'src/pages/invoice/orderandinvoiceList.php:can_view_business_data'
        'src/pages/firm/firmandcustomerAdd.php:can_view_business_data'
        'src/pages/firm/firmandcustomerDel.php:can_view_business_data'
        'src/pages/firm/firmandcustomerEdit.php:can_view_business_data'
        'src/pages/firm/firmandcustomerList.php:can_view_business_data'
        'src/pages/user/adminAdd.php:can_manage_users'
        'src/pages/user/adminDel.php:can_manage_users'
        'src/pages/user/adminDelBatch.php:can_manage_users'
        'src/pages/user/adminEdit.php:can_manage_users'
        'src/pages/user/adminList.php:can_manage_users'
        'src/pages/profile/profile.php:can_access_self'
        'src/pages/profile/profileDelete.php:can_access_self'
        'src/pages/profile/profileEdit.php:can_access_self'
    )
    for entry in "${expected_guards[@]}"; do
        file="${entry%%:*}"
        function_name="${entry##*:}"
        [[ -f "$APP_DIR/$file" ]] || { failures+="$file:缺檔 "; continue; }
        checked=$((checked + 1))
        if ! grep -Eq "if[[:space:]]*\\([[:space:]]*!?[[:space:]]*${function_name}\\(\\)[[:space:]]*\\)" "$APP_DIR/$file"; then
            failures+="$file→$function_name "
        fi
    done
    # 反向：列舉 src/pages/ 下的每個 .php，確認沒有頁面檔漏掉守衛（清單母體＝檔案系統，不是 grep 特徵）。
    local f rel
    while IFS= read -r f; do
        rel="${f#"$APP_DIR"/}"
        grep -Eq "if[[:space:]]*\\([[:space:]]*!?[[:space:]]*(can_[a-z_]+|is_admin)\\(\\)[[:space:]]*\\)" "$f" \
            || failures+="$rel:未呼叫任何具名守衛 "
    done < <(find "$APP_DIR/src/pages" -type f -name '*.php' | sort)
    [[ "$checked" -ge 43 ]] || { CHECK_DETAIL="只核對到 $checked 個檔（預期 43），清單或路徑有誤"; return 1; }
    [[ -z "$failures" ]] || { CHECK_DETAIL="具名權限守衛缺失：${failures% }"; return 1; }
    CHECK_DETAIL="$checked 個頁面檔均使用指定的具名守衛；src/pages/ 全域列舉無漏網"
}

is_forbidden_without_application_content() {
    local body="$1"
    # HTTP 200 不能證明拒絕；必須有拒絕訊號，且不得混入頁面結構或互動元件。
    [[ "$body" == *"權限不足!"* ]] || return 1
    ! grep -Eiq '<(form|table|div|h[1-6]|input|select|button|script)([[:space:]>])' <<< "$body"
}

check_direct_file_access() {
    # 母體＝檔案系統列舉，不是寫死清單。列舉整個 repo 的每個 .php：
    #   - src/**、scripts/**：在 DocumentRoot 之外，任何檔案從網路上都必須是 HTTP 404
    #   - public/**：只有 PUBLIC_ENTRY_EXEMPTIONS 那幾個入口可達；其餘 public/*.php
    #     （例如頁面檔被誤放進 docroot）必須 404
    # 這樣「頁面檔被搬回/誤放到 public/」會被抓到，而不是只驗固定幾個檔名。
    local f rel base status failures="" checked=0
    while IFS= read -r f; do
        rel="${f#"$APP_DIR"/}"
        case "$rel" in
            public/*)
                base="${rel#public/}"
                # public/css、public/js 底下沒有 .php；這裡實際只會是 public/<name>.php
                [[ "$base" == */* ]] && continue
                checked=$((checked + 1))
                status="$(curl -sS -o "$TMP_DIR/direct-$base.html" -w '%{http_code}' "$BASE_URL/$base")" \
                    || { failures+="$rel:curl "; continue; }
                if [[ -n "${PUBLIC_ENTRY_EXEMPTIONS[$base]+set}" ]]; then
                    [[ "$status" =~ ^[23][0-9]{2}$ ]] \
                        || failures+="$rel:入口應可達但為 HTTP $status "
                else
                    [[ "$status" == "404" ]] \
                        || failures+="$rel:非入口的 public 檔可達(HTTP $status)——頁面檔不該放進 docroot "
                fi
                ;;
            src/*|scripts/*)
                checked=$((checked + 1))
                status="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/$rel")" \
                    || { failures+="$rel:curl "; continue; }
                [[ "$status" == "404" ]] \
                    || failures+="$rel:DocumentRoot 外的檔案竟可達(HTTP $status) "
                ;;
        esac
    done < <(find "$APP_DIR" -type f -name '*.php' \
                 -not -path '*/.git/*' -not -path '*/_ops/*' | sort)
    [[ "$checked" -ge 40 ]] \
        || { CHECK_DETAIL="只列舉到 $checked 個 .php（預期約 50），find 範圍可能有誤"; return 1; }
    [[ -z "$failures" ]] || { CHECK_DETAIL="直接存取未被擋下：${failures% }"; return 1; }
    CHECK_DETAIL="列舉 $checked 個 .php：src/、scripts/ 全部 404；public/ 僅 index/login/logout/register 可達"
}

check_external_direct_business_access() {
    # WO-10 後：業務頁面檔已移出 DocumentRoot，對任何角色（含 limited=3）直接請求都應 404，
    # 不論走舊的平面路徑還是新的 src/ 路徑。
    local s_old s_new
    s_old="$(curl -sS -b "$EXTERNAL_COOKIE_JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/ProductEdit.php?id=1")" \
        || { CHECK_DETAIL="舊路徑 /ProductEdit.php curl 失敗"; return 1; }
    s_new="$(curl -sS -b "$EXTERNAL_COOKIE_JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/src/pages/product/ProductEdit.php?id=1")" \
        || { CHECK_DETAIL="新路徑 /src/pages/product/ProductEdit.php curl 失敗"; return 1; }
    [[ "$s_old" == "404" && "$s_new" == "404" ]] \
        || { CHECK_DETAIL="limited=3 直接開 ProductEdit.php：/ProductEdit.php=$s_old、/src/pages/product/ProductEdit.php=$s_new（預期皆 404）"; return 1; }
    CHECK_DETAIL="limited=3 直接開 ProductEdit.php：舊路徑與 src/ 路徑皆 404"
}

check_external_registration() {
    local state status
    delete_external_user
    status="$(curl -sS -L -o "$TMP_DIR/external-register.html" -w '%{http_code}' \
        --data-urlencode 'name=Smoke External' --data-urlencode "id=$EXTERNAL_ID" \
        --data-urlencode "pw=$EXTERNAL_PASSWORD" --data-urlencode 'email=smoke-external@example.test' \
        --data-urlencode 'party_type=廠商' \
        "$BASE_URL/index.php?Act=160")" || { CHECK_DETAIL="curl 無法送出外部使用者註冊"; return 1; }
    state="$(php -r '
        require "/var/www/app/src/config.inc.php";
        $stmt = $pdo->prepare("SELECT limited || \":\" || party_type FROM User WHERE id = :id ORDER BY prikey DESC LIMIT 1");
        $stmt->execute([":id" => $argv[1]]);
        echo $stmt->fetchColumn();
    ' "$EXTERNAL_ID")" || { CHECK_DETAIL="無法讀取外部使用者註冊資料"; return 1; }
    [[ "$status" =~ ^2[0-9]{2}$ && "$state" == "3:廠商" ]] || { CHECK_DETAIL="HTTP $status、註冊資料=$state（預期 3:廠商）"; return 1; }
    CHECK_DETAIL="HTTP $status、limited=3、party_type=廠商"
}

check_external_login() {
    local body status
    status="$(curl -sS -L -c "$EXTERNAL_COOKIE_JAR" -b "$EXTERNAL_COOKIE_JAR" -o "$TMP_DIR/external-login.html" -w '%{http_code}' \
        --data-urlencode "admid=$EXTERNAL_ID" --data-urlencode "admpw=$EXTERNAL_PASSWORD" --data-urlencode 'btemplogin=1' \
        "$BASE_URL/login.php")" || { CHECK_DETAIL="curl 無法登入外部使用者"; return 1; }
    body="$(<"$TMP_DIR/external-login.html")"
    [[ "$status" =~ ^2[0-9]{2}$ && "$body" == *"歡迎"* && "$(grep -c 'PHPSESSID' "$EXTERNAL_COOKIE_JAR" || true)" -ge 1 ]] || { CHECK_DETAIL="HTTP $status、PHPSESSID 或「歡迎」缺失"; return 1; }
    CHECK_DETAIL="HTTP $status、外部使用者取得 PHPSESSID、頁面含「歡迎」"
}

check_external_visibility() {
    local body status route failures=""
    # WO-10 後 profile.php 只能經前端控制器；外部使用者看自己的個人資料走 index.php?Act=100。
    body="$(curl -sS -b "$EXTERNAL_COOKIE_JAR" -w $'\n%{http_code}' "$BASE_URL/index.php?Act=100")" || { CHECK_DETAIL="index.php?Act=100 curl 失敗"; return 1; }
    status="${body##*$'\n'}"
    body="${body%$'\n'*}"
    if [[ ! "$status" =~ ^2[0-9]{2}$ || "$body" != *"個人資料"* || "$body" == *"權限不足!"* ]]; then
        failures+="Act=100:$status "
    fi
    for route in 'index.php?Act=300' 'index.php?Act=200' 'index.php?Act=240'; do
        body="$(curl -sS -b "$EXTERNAL_COOKIE_JAR" -w $'\n%{http_code}' "$BASE_URL/$route")" || { failures+="$route:curl "; continue; }
        status="${body##*$'\n'}"
        body="${body%$'\n'*}"
        if [[ ! "$status" =~ ^2[0-9]{2}$ || "$body" != *"權限不足!"* ]]; then
            failures+="$route:$status "
        fi
    done
    [[ -z "$failures" ]] || { CHECK_DETAIL="外部使用者可見範圍不符：${failures% }"; return 1; }
    CHECK_DETAIL="Act=100 個人資料可見；Act=300、200、240 均顯示「權限不足!」"
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
        require "/var/www/app/src/config.inc.php";
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

# WO-11：英文模式下，指定頁面的「介面文字」不得殘留 CJK 字元。
#
# 「介面文字」與「資料庫資料」怎麼區分：
#   介面文字＝寫在 PHP 檔裡、經 t() 取用的字串；漏翻時會以 zh-TW 值回退，
#   於是在英文頁面顯示為中文。資料庫資料＝顧客名、地址、廠商名等 seed / 使用者
#   輸入的值，本來就可能是任何語言，不該被當成「漏翻」。
#
# 做法：先向 SQLite 動態列舉每張表每個欄位，撈出所有「含 CJK 的值」，
#       從英文模式抓回的 HTML 中把這些子字串剝掉，再斷言剩餘內容不含
#       [\x{4e00}-\x{9fff}]。這樣對「產品名是英文、廠商名是中文」不會誤判：
#       我們剝除的是所有 DB 文字值、不論其語言。
# 另外剝除：
#   - HTML 註解（<!-- -->）：不是使用者可見文字；會回報剝除的則數，不靜默。
#   - 語言選單裡刻意以原文呈現的語言自稱（繁體中文）：語言選單顯示目標語言的
#     原生名稱是正確的 i18n 慣例，不是漏翻。這是唯一一個明文允許清單項目。
I18N_CJK_ALLOWLIST=$'繁體中文'

check_i18n_english_no_cjk() {
    # 依賴檢查 7a 建立的已登入 $COOKIE_JAR。
    local db_values page name route mode html report residual ctx comments
    local failures="" scanned=0 total_comments=0
    local US=$'\x1f'

    db_values="$(php -r '
        require "/var/www/app/src/config.inc.php";
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type=\"table\" AND name NOT LIKE \"sqlite_%\"")->fetchAll(PDO::FETCH_COLUMN);
        $seen = [];
        foreach ($tables as $t) {
            foreach ($pdo->query("SELECT * FROM \"$t\"", PDO::FETCH_ASSOC) as $row) {
                foreach ($row as $v) {
                    if (is_string($v) && preg_match("/[\x{4e00}-\x{9fff}]/u", $v)) {
                        $seen[$v] = strlen($v);
                    }
                }
            }
        }
        arsort($seen);
        foreach (array_keys($seen) as $v) { echo $v, "\n"; }
    ')" || { CHECK_DETAIL="無法列舉資料庫 CJK 值"; return 1; }

    # <name>|<route>|<cookie|nocookie>
    for page in \
        "login${US}index.php?lang=en${US}nocookie" \
        "home${US}index.php?Act=150&lang=en${US}cookie" \
        "customer-list${US}index.php?Act=300&lang=en${US}cookie" \
        "customer-add${US}index.php?Act=320&lang=en${US}cookie" \
        "order-list${US}index.php?Act=430&lang=en${US}cookie" \
        "order-add${US}index.php?Act=440&lang=en${US}cookie" \
        "invoice-list${US}index.php?Act=240&lang=en${US}cookie" \
        "firm-customer-list${US}index.php?Act=200&lang=en${US}cookie" \
        "user-list${US}index.php?Act=110&lang=en${US}cookie" \
        "profile${US}index.php?Act=100&lang=en${US}cookie"; do
        IFS="$US" read -r name route mode <<< "$page"
        if [[ "$mode" == "nocookie" ]]; then
            html="$(curl -sS "$BASE_URL/$route")" || { failures+="$name:curl "; continue; }
        else
            html="$(curl -sS -b "$COOKIE_JAR" "$BASE_URL/$route")" || { failures+="$name:curl "; continue; }
        fi
        scanned=$((scanned + 1))
        report="$(DB_VALUES="$db_values" ALLOW="$I18N_CJK_ALLOWLIST" php -r '
            $html = stream_get_contents(STDIN);
            $comments = 0;
            $strip = function ($re) use (&$html, &$comments) {
                $html = preg_replace_callback($re, function ($m) use (&$comments) {
                    if (preg_match("/[\x{4e00}-\x{9fff}]/u", $m[0])) { $comments++; }
                    return "";
                }, $html);
            };
            // 非使用者可見文字：HTML 註解、CSS/JS 區塊註解、含中文的 // 行註解。
            // 這些依 WO-11 第 7 點屬「註解，不算漏字串」；剝除但回報則數，不靜默。
            $strip("/<!--.*?-->/s");
            $strip("#/\*.*?\*/#s");
            $strip("#//[^\n]*[\x{4e00}-\x{9fff}][^\n]*#u");
            foreach (preg_split("/\n/", (string) getenv("DB_VALUES"), -1, PREG_SPLIT_NO_EMPTY) as $v) {
                $html = str_replace($v, "", $html);
            }
            foreach (preg_split("/\n/", (string) getenv("ALLOW"), -1, PREG_SPLIT_NO_EMPTY) as $v) {
                $html = str_replace($v, "", $html);
            }
            preg_match_all("/[\x{4e00}-\x{9fff}]/u", $html, $mm);
            $n = count($mm[0]);
            $ctx = "";
            if ($n > 0 && preg_match("/.{0,50}[\x{4e00}-\x{9fff}].{0,50}/su", $html, $c)) {
                $ctx = preg_replace("/\s+/", " ", $c[0]);
            }
            echo $n, "\x1f", $ctx, "\x1f", $comments;
        ' <<< "$html")"
        IFS="$US" read -r residual ctx comments <<< "$report"
        total_comments=$((total_comments + ${comments:-0}))
        if [[ "${residual:-1}" != "0" ]]; then
            failures+="${name}:殘留${residual}字(…${ctx}…) "
        fi
    done

    [[ "$scanned" -ge 10 ]] || { CHECK_DETAIL="只掃到 $scanned 頁（預期 10）"; return 1; }
    [[ -z "$failures" ]] || { CHECK_DETAIL="英文模式仍有中文介面文字：${failures% }"; return 1; }
    CHECK_DETAIL="10 頁英文模式均無 CJK 介面殘留（已剝除 DB 值；另剝除 $total_comments 則含中文的 HTML 註解）"
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
        $index = file_get_contents("/var/www/app/.git/index");
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
    '8 check_invoice_snapshots' \
    '9 check_password_hash' \
    'AUTH-GUARDS check_named_permission_guards' \
    'EXTERNAL-REGISTER check_external_registration' \
    'EXTERNAL-LOGIN check_external_login' \
    'EXTERNAL-VISIBILITY check_external_visibility' \
    'DIRECT-ACCESS check_direct_file_access' \
    'EXTERNAL-DIRECT-ACCESS check_external_direct_business_access' \
    '10 check_injection' \
    'ORDER-TOTALS check_order_totals' \
    'ROUTES check_routes' \
    'I18N-EN-CJK check_i18n_english_no_cjk' \
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
