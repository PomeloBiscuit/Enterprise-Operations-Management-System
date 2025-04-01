<?php
session_start(); // 確保會話已啟動
require_once("config.inc.php"); // 引入資料庫設定檔

if (isset($_SESSION["admlimit"]) && $_SESSION["admlimit"] > 0) {
    if (isset($_POST['selectedShipments'])) {
        $selectedShipments = $_POST['selectedShipments'];
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedShipments)), ',');
            $stmt = $pdo->prepare("DELETE FROM Shipment WHERE ShipmentID IN ($placeholders)");
            $stmt->execute($selectedShipments);
            header("Location: index.php?Act=470");
            exit();
        } catch (PDOException $e) {
            echo "<p>錯誤：" . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>未選擇任何出貨紀錄！</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>
