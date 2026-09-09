<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Employee WHERE EmployeeID = :EmployeeID");
        $stmt->execute([':EmployeeID' => $_GET['id']]);
        header("Location: index.php?Act=350");
        exit();
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>" . t('common.invalid_id') . "</p>";
}
?>
