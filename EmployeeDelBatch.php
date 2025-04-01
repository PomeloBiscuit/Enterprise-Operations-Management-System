<?php
if ($_SESSION["admlimit"] > 0) { // 判斷是否有登入
    if (isset($_POST['selectedEmployees'])) { // 判斷是否有選擇員工
        $selectedEmployees = $_POST['selectedEmployees']; // 取得選擇的員工
        $resultsPerPage = $_POST['resultsPerPage'] ?? 5; // 預設顯示 5 筆
        try { // 執行資料庫 DELETE
            $placeholders = rtrim(str_repeat('?,', count($selectedEmployees)), ','); // 產生佔位符
            $stmt = $pdo->prepare("DELETE FROM Employee WHERE EmployeeID IN ($placeholders)"); // 準備 SQL
            $stmt->execute($selectedEmployees); // 執行 SQL
            header("Location: index.php?Act=350&resultsPerPage=$resultsPerPage"); // 轉址回員工列表
            exit(); // 結束程式
        } catch (PDOException $e) { // 資料庫錯誤
            echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
        }
    } else {
        echo "<p>未選擇任何員工！</p>"; // 顯示錯誤訊息
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>"; // 顯示錯誤訊息
}
?>
