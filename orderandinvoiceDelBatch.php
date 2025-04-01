<?php
if ($_SESSION["admlimit"] > 0) {
    if (isset($_POST['selectedOrders'])) {
        $selectedOrders = $_POST['selectedOrders'];
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedOrders)), ',');
            $stmt = $pdo->prepare("DELETE FROM orderandinvoice WHERE id IN ($placeholders)");
            $stmt->execute($selectedOrders);
            header("Location: index.php?Act=240");
            exit();
        } catch (PDOException $e) {
            echo "<p>錯誤：" . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>未選擇任何訂單！</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>
