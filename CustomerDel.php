<?php
if (isset($_GET['id'])) { // 若有 ID
    try { // 嘗試執行
        $stmt = $pdo->prepare("DELETE FROM customer WHERE customer_id = :id"); // SQL 語法
        $stmt->execute([':id' => $_GET['id']]); 
        header("Location: CustomerList.php");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
