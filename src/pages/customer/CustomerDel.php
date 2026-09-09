<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if (isset($_GET['id'])) { // 若有 ID
    try { // 嘗試執行
        $stmt = $pdo->prepare("DELETE FROM customer WHERE customer_id = :id"); // SQL 語法
        $stmt->execute([':id' => $_GET['id']]); 
        header("Location: index.php?Act=300");
        exit();
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>" . t('common.invalid_id') . "</p>";
}
?>
