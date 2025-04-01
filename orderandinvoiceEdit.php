<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE orderandinvoice
            SET order_number = :order_number,
                invoice_number = :invoice_number,
                customer_name = :customer_name,
                amount = :amount,
                status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':order_number' => $_POST['order_number'],
            ':invoice_number' => $_POST['invoice_number'],
            ':customer_name' => $_POST['customer_name'],
            ':amount' => $_POST['amount'],
            ':status' => isset($_POST['status']) ? 1 : 0,
            ':id' => $_POST['id']
        ]);
        header("Location: index.php?Act=240");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM orderandinvoice WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $row = $stmt->fetch();
}
?>

<h3>編輯訂單與發票</h3>
<form method="POST">
    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
    <label>訂單號碼</label>
    <input type="text" name="order_number" class="form-control" value="<?php echo $row['order_number']; ?>" required>
    <label>發票號碼</label>
    <input type="text" name="invoice_number" class="form-control" value="<?php echo $row['invoice_number']; ?>" required>
    <label>客戶名稱</label>
    <input type="text" name="customer_name" class="form-control" value="<?php echo $row['customer_name']; ?>" required>
    <label>金額</label>
    <input type="number" name="amount" class="form-control" value="<?php echo $row['amount']; ?>" required>
    <label>狀態</label>
    <input type="checkbox" name="status" <?php echo $row['status'] ? 'checked' : ''; ?>> 完成
    <br>
    <button type="submit" class="btn btn-primary">更新</button>
</form>
