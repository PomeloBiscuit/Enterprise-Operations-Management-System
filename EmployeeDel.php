<?php
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Employee WHERE EmployeeID = :EmployeeID");
        $stmt->execute([':EmployeeID' => $_GET['id']]);
        header("Location: index.php?Act=350");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>無效的 ID！</p>";
}
?>
