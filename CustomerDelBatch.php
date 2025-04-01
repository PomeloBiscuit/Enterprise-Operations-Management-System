<?php // CustomerDelBatch.php
if ($_SESSION["admlimit"] > 0) { // 管理員才能刪除
    if (isset($_POST['selectedCustomers'])) { // 若有選擇顧客
        $selectedCustomers = $_POST['selectedCustomers']; // 取得選擇的顧客
        $resultsPerPage = $_POST['resultsPerPage'] ?? 5; // 預設顯示 5 筆
        try { // 嘗試執行
            $placeholders = rtrim(str_repeat('?,', count($selectedCustomers)), ','); // 產生佔位符
            $stmt = $pdo->prepare("DELETE FROM Customer WHERE CustomerID IN ($placeholders)"); // SQL 語法
            $stmt->execute($selectedCustomers); // 執行 SQL 語法
            header("Location: index.php?Act=300&resultsPerPage=$resultsPerPage"); // 導向顧客列表
            exit(); // 結束程式
        } catch (PDOException $e) { // 若有錯誤
            echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
        } // 結束執行
    } else { // 若未選擇顧客
        echo "<p>未選擇任何顧客！</p>"; // 顯示錯誤訊息
    } // 結束判斷是否有選擇顧客
} else { // 若非管理員
    echo "<p style='text-align:center; color:red;'>權限不足!</p>"; // 顯示錯誤訊息
} // 結束判斷是否為管理員
?> <!-- 結束 PHP 區塊 -->
