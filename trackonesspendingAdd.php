<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tksg (item, value, quantity, date, time, checkid)
            VALUES (:item, :value, :quantity, :date, :time, :checkid)
        ");
        $stmt->execute([
            ':item' => $_POST['item'],
            ':value' => $_POST['value'],
            ':quantity' => $_POST['quantity'],
            ':date' => $_POST['date'],
            ':time' => $_POST['time'],
            ':checkid' => $_POST['checkid']
        ]);
        header("Location: index.php?Act=280");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
}
?>

<h3>新增流水帳</h3>
<form method="POST">
    <label>項目</label>
    <input type="text" name="item" class="form-control" required>
    <label>金額</label>
    <input type="number" name="value" class="form-control" required>
    <label>數量</label>
    <input type="number" name="quantity" class="form-control" required>
    <label>日期</label>
    <input type="date" name="date" class="form-control" required>
    <label>時間</label>
    <input type="time" name="time" class="form-control" required>
    <label>帳目編號</label>
    <input type="text" name="checkid" class="form-control" required>
    <br>
    <button type="submit" class="btn btn-primary">新增</button>
</form>
