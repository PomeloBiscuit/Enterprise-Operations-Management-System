<?php
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Shipment WHERE ShipmentID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=470");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
