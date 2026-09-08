<?php
require_once __DIR__ . '/auth.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>權限不足!</p>";
    exit;
}
if (can_view_business_data()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // 快照一律由外鍵指向的現行資料取得，不接受瀏覽器送來的名稱或編號。
            $snapshotStmt = $pdo->prepare("
                SELECT o.OrderID AS order_number, c.CustomerName AS customer_name
                FROM Orders o CROSS JOIN Customer c
                WHERE o.OrderID = :order_id AND c.CustomerID = :customer_id
            ");
            $snapshotStmt->execute([
                ':order_id' => (int) $_POST['order_id'],
                ':customer_id' => (int) $_POST['customer_id'],
            ]);
            $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);
            if ($snapshot === false) {
                throw new RuntimeException('所選訂單或客戶不存在。');
            }

            $stmt = $pdo->prepare("
                INSERT INTO orderandinvoice
                    (order_id, order_number, invoice_number, customer_id, customer_name, amount, status, created_at)
                VALUES
                    (:order_id, :order_number, :invoice_number, :customer_id, :customer_name, :amount, :status, datetime('now','localtime'))
            ");
            $stmt->execute([
                ':order_id' => (int) $_POST['order_id'],
                ':order_number' => $snapshot['order_number'],
                ':invoice_number' => $_POST['invoice_number'],
                ':customer_id' => (int) $_POST['customer_id'],
                ':customer_name' => $snapshot['customer_name'],
                ':amount' => $_POST['amount'],
                ':status' => $_POST['status']
            ]);
            header("Location: index.php?Act=240");
            exit();
        } catch (Throwable $e) {
            echo "<p>新增失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // 取得下一個自動生成 ID（SQLite：sqlite_sequence 取代 information_schema）
    $stmt = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'orderandinvoice'), 0) + 1 AS AUTO_INCREMENT");
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
