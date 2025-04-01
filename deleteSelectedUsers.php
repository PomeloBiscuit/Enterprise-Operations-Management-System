<?php
session_start(); // 確保會話已啟動
require_once("config.inc.php"); // 引入資料庫設定檔

if (isset($_SESSION["admlimit"]) && $_SESSION["admlimit"] > 0) {
    if (isset($_POST['selectedUsers'])) {
        $selectedUsers = $_POST['selectedUsers'];
        $resultsPerPage = $_POST['resultsPerPage']; // 新增此行
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedUsers)), ',');
            $stmt = $pdo->prepare("DELETE FROM User WHERE prikey IN ($placeholders)");
            $stmt->execute($selectedUsers);
            header("Location: index.php?Act=110&resultsPerPage=$resultsPerPage"); // 維持顯示筆數
            exit();
        } catch (PDOException $e) {
            echo "<p>錯誤：" . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>未選擇任何使用者！</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>
