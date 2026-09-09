<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
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
                throw new RuntimeException(t('invoice.add.err_not_found'));
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
            echo "<p>" . t('invoice.add.fail_prefix') . htmlspecialchars($e->getMessage()) . "</p>";
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
                <h3><?php echo t('invoice.add.title'); ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>InvoiceID</label>
                        <input type="text" name="invoice_id" class="form-control" value="<?php echo $nextId; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('invoice.add.order_label'); ?></label>
                        <select name="order_id" class="form-control select2" required>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?php echo $order['OrderID']; ?>"><?php echo $order['TrackingNumber']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('invoice.field.invoice_number'); ?></label>
                        <input type="text" name="invoice_number" class="form-control" placeholder="<?php echo htmlspecialchars(t('invoice.add.invoice_number_ph')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('invoice.add.customer_label'); ?></label>
                        <select name="customer_id" class="form-control select2" required>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['CustomerID']; ?>"><?php echo $customer['CustomerName']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('invoice.field.amount'); ?></label>
                        <input type="number" name="amount" class="form-control" placeholder="<?php echo htmlspecialchars(t('invoice.add.amount_ph')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('invoice.field.status'); ?></label>
                        <select name="status" class="form-control" required>
                            <option value="1"><?php echo t('invoice.status.done'); ?></option>
                            <option value="0"><?php echo t('invoice.status.pending'); ?></option>
                        </select>
                    </div>
                    <br>
                    <div class="text-center">
                        <a href="index.php?Act=240" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="reset" class="btn btn-warning"><?php echo t('common.clear'); ?></button>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="submit" class="btn btn-primary"><?php echo t('common.submit'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
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
