<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=390");
        exit();
    } catch (PDOException $e) {
        echo "<p>" . user_safe_error($e) . "</p>";
    }
} else {
    echo "<p>" . t('common.invalid_id') . "</p>";
}
?>
