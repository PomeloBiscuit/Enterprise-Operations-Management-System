<?php
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=390");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
