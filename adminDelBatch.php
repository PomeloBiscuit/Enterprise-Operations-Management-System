<?php
if ($_SESSION["admlimit"] > 0) {
    if (isset($_POST['selectedUsers'])) {
        $selectedUsers = $_POST['selectedUsers'];
        $resultsPerPage = $_POST['resultsPerPage'] ?? 10; // 預設顯示 10 筆
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedUsers)), ',');
            $stmt = $pdo->prepare("DELETE FROM User WHERE prikey IN ($placeholders)");
            $stmt->execute($selectedUsers);
            header("Location: index.php?Act=110&resultsPerPage=$resultsPerPage");
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
