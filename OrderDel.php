<?php
if (isset($_GET['id'])) {
    $resultsPerPage = $_GET['resultsPerPage'] ?? 5;
    try {
        $stmt = $pdo->prepare("DELETE FROM Orders WHERE OrderID = :id");
        $stmt->execute([':id' => $_GET['id']]);
        header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
