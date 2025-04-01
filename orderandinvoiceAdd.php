<?php
if ($_SESSION["admlimit"] > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO orderandinvoice (order_id, invoice_number, customer_id, amount, status, created_at)
                VALUES (:order_id, :invoice_number, :customer_id, :amount, :status, NOW())
            ");
            $stmt->execute([
                ':order_id' => $_POST['order_id'],
                ':invoice_number' => $_POST['invoice_number'],
                ':customer_id' => $_POST['customer_id'],
                ':amount' => $_POST['amount'],
                ':status' => $_POST['status']
            ]);
            header("Location: index.php?Act=240");
            exit();
        } catch (PDOException $e) {
            echo "<p>新增失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // 取得最新的自動生成 ID
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'orderandinvoice'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = $row['AUTO_INCREMENT'];

    // 取得所有訂單和顧客資料
    $orders = $pdo->query("SELECT OrderID, TrackingNumber FROM Orders")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="container mt-5">
        <div class="card" style="border-radius: 15px;">
            <div class="card-header text-center">
                <h3>新增訂單與發票</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>InvoiceID</label>
                        <input type="text" name="invoice_id" class="form-control" value="<?php echo $nextId; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>訂單號碼 (OrderID)</label>
                        <select name="order_id" class="form-control select2" required>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?php echo $order['OrderID']; ?>"><?php echo $order['TrackingNumber']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>發票號碼</label>
                        <input type="text" name="invoice_number" class="form-control" placeholder="請輸入發票號碼" required>
                    </div>
                    <div class="form-group">
                        <label>客戶ID</label>
                        <select name="customer_id" class="form-control select2" required>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['CustomerID']; ?>"><?php echo $customer['CustomerName']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>金額</label>
                        <input type="number" name="amount" class="form-control" placeholder="請輸入金額" required>
                    </div>
                    <div class="form-group">
                        <label>狀態</label>
                        <select name="status" class="form-control" required>
                            <option value="1">已完成</option>
                            <option value="0">未完成</option>
                        </select>
                    </div>
                    <br>
                    <div class="text-center">
                        <a href="index.php?Act=240" class="btn btn-secondary">返回</a>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="reset" class="btn btn-warning">清除</button>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="submit" class="btn btn-primary">送出</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>

<!-- 引入 Select2 CSS 和 JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2();
});
</script>
