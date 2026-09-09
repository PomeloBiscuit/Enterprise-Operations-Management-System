<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Shipment WHERE ShipmentID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=470");
        exit();
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>" . t('common.invalid_id') . "</p>";
}
?>
