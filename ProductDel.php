<?php
require_once __DIR__ . '/auth.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>權限不足!</p>";
    exit;
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=390");
        exit();
    } catch (PDOException $e) {
        // Contain.ProductID 是 ON DELETE RESTRICT：被訂單引用的產品不准刪。
        if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
            echo "<p>無法刪除：仍有訂單引用此產品。請先移除相關訂單的該項明細。</p>";
        } else {
            echo "<p>錯誤：" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
