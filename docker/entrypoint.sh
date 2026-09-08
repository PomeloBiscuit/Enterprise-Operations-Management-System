#!/bin/bash
set -e

APP_DIR=/var/www/html
DB_FILE="$APP_DIR/data/fiance2024.sqlite"
CONFIG="$APP_DIR/config.inc.php"

# config.inc.php 在 .gitignore 內，所以「全新 clone」不會有這個檔，
# 而 index.php 第一行就 require_once 它 —— 少了它整站直接 fatal error。
# SQLite 版的範本沒有任何需要填的值，所以這裡直接複製，
# 讓「clone 之後一道 docker compose up」真的成立（實測過全新 clone 會缺這個檔）。
if [ ! -f "$CONFIG" ]; then
    echo "[entrypoint] config.inc.php 不存在，從 config.inc.php.example 複製..."
    cp "$APP_DIR/config.inc.php.example" "$CONFIG"
fi

# data/ 目錄要能被 www-data 寫入（SQLite 的新增/修改/刪除都要寫檔）
mkdir -p "$APP_DIR/data"
chmod -R 0777 "$APP_DIR/data"

# 第一次啟動（資料庫檔還不存在）自動建表 + 塞種子資料，
# 讓「clone 之後一道 docker compose up 就有資料的畫面」成立。
# 要重建資料庫時，在瀏覽器開 /create.php 即可（會刪掉舊檔重來）。
if [ ! -f "$DB_FILE" ]; then
    echo "[entrypoint] 資料庫不存在，執行 create.php 建立種子資料..."
    php "$APP_DIR/create.php" > /tmp/create.out 2>&1 || true
    grep -i -E "error|exception" /tmp/create.out && echo "[entrypoint] ⚠ create.php 輸出含 error，請檢查 /tmp/create.out" || echo "[entrypoint] create.php 完成，無 error。"
    chmod -R 0777 "$APP_DIR/data"
fi

# 交回官方 image 的預設啟動流程（apache2-foreground）
exec docker-php-entrypoint "$@"
