<?php // ProductDelBatch.php
session_start(); // 確保會話已啟動
require_once("config.inc.php"); // 引入資料庫設定檔

if (isset($_SESSION["admlimit"]) && $_SESSION["admlimit"] > 0) { // 判斷權限
    if (isset($_POST['selectedProducts'])) { // 判斷是否有選擇貨物
        $selectedProducts = $_POST['selectedProducts']; // 取得選擇的貨物
        $resultsPerPage = $_POST['resultsPerPage']; // 新增此行
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedProducts)), ','); // 產生問號字串
            $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID IN ($placeholders)"); // 刪除貨物
            $stmt->execute($selectedProducts); // 執行 SQL
            header("Location: index.php?Act=390&resultsPerPage=$resultsPerPage"); // 維持顯示筆數
            exit(); // 結束程式
        } catch (PDOException $e) { // 例外處理
            echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
        } 
    } else { // 如果未選擇任何貨物
        echo "<p>未選擇任何貨物！</p>"; // 顯示錯誤訊息
    }   
} else { // 如果權限不足
    echo "<p style='text-align:center; color:red;'>權限不足!</p>"; // 顯示錯誤訊息
} 
?> 



