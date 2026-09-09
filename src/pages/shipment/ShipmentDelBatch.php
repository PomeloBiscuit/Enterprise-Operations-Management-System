<?php
session_start(); // 確保會話已啟動
require_once __DIR__ . '/../../config.inc.php'; // 引入資料庫設定檔
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';

if (can_view_business_data()) {
    if (isset($_POST['selectedShipments'])) {
        $selectedShipments = $_POST['selectedShipments'];
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedShipments)), ',');
            $stmt = $pdo->prepare("DELETE FROM Shipment WHERE ShipmentID IN ($placeholders)");
            $stmt->execute($selectedShipments);
            header("Location: index.php?Act=470");
            exit();
        } catch (PDOException $e) {
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>" . t('shipment.none_selected') . "</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>
