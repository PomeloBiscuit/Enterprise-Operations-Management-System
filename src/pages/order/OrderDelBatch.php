<?php // OrderDelBatch.php
session_start(); // 確保會話已啟動
require_once __DIR__ . '/../../config.inc.php'; // 引入資料庫設定檔
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';

if (can_view_business_data()) {    // 管理員與內部員工才可操作業務資料
    if (isset($_POST['selectedOrders'])) {  // 判斷是否有 POST 表單
        $selectedOrders = $_POST['selectedOrders']; // 取得選擇的訂單
        $resultsPerPage = $_POST['resultsPerPage']; // 取得顯示筆數
        try {   // 例外處理
            $placeholders = rtrim(str_repeat('?,', count($selectedOrders)), ',');   // 產生問號字串
            $stmt = $pdo->prepare("DELETE FROM Orders WHERE OrderID IN ($placeholders)");   // 刪除訂單
            $stmt->execute($selectedOrders);    // 執行 SQL
            header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage");   // 顯示訂單列表
            exit(); // 結束程式
        } catch (PDOException $e) { // 例外處理
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";   // 顯示錯誤訊息
        }   // 結束例外處理
    } else {    // 如果未 POST 表單
        echo "<p>" . t('order.none_selected') . "</p>";  // 顯示錯誤訊息
    }   // 結束判斷是否有 POST 表單
} else {    // 如果未登入
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";  // 顯示錯誤訊息
}   // 結束判斷是否有登入
?>



