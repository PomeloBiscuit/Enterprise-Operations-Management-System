<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE tksg
            SET item = :item, value = :value, quantity = :quantity, date = :date, time = :time, checkid = :checkid
            WHERE prikey = :id
        ");
        $stmt->execute([
            ':item' => $_POST['item'],
            ':value' => $_POST['value'],
            ':quantity' => $_POST['quantity'],
            ':date' => $_POST['date'],
            ':time' => $_POST['time'],
            ':checkid' => $_POST['checkid'],
            ':id' => $_POST['id']
        ]);
        header("Location: index.php?Act=280");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM tksg WHERE prikey = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $row = $stmt->fetch();
}
?>

<h3>編輯流水帳</h3>
<form method="POST">
    <input type="hidden" name="id" value="<?php echo $row['prikey']; ?>">
    <label>項目</label>
    <input type="text" name="item" class="form-control" value="<?php echo $row['item']; ?>" required>
    <label>金額</label>
    <input type="number" name="value" class="form-control" value="<?php echo $row['value']; ?>" required>
    <label>數量</label>
    <input type="number" name="quantity" class="form-control" value="<?php echo $row['quantity']; ?>" required>
    <label>日期</label>
    <input type="date" name="date" class="form-control" value="<?php echo $row['date']; ?>" required>
    <label>時間</label>
    <input type="time" name="time" class="form-control" value="<?php echo $row['time']; ?>" required>
    <label>帳目編號</label>
    <input type="text" name="checkid" class="form-control" value="<?php echo $row['checkid']; ?>" required>
    <br>
    <button type="submit" class="btn btn-primary">更新</button>
</form>
