<?php
if ($_SESSION["admlimit"] > 0) {
    $EK = intval($_GET['EK']);
    try {
        $aa = "UPDATE User SET enabled=0 WHERE prikey='{$EK}'";
        $pdo->exec($aa);
    } catch (PDOException $e) {
        $output = "Error deleting admin: " . $e->getMessage();
        echo "<p>$output";
    }
    header("refresh:1; url=index.php?Act=110");
} else {
    echo "<p align='center'>權限不足!</p>";
}
?>
