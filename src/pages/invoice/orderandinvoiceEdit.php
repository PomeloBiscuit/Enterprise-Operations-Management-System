<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 改變關聯等同重新開立關聯內容，因此以新外鍵重新取得兩個快照。
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
            UPDATE orderandinvoice
            SET order_id = :order_id,
                order_number = :order_number,
                invoice_number = :invoice_number,
                customer_id = :customer_id,
                customer_name = :customer_name,
                amount = :amount,
                status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':order_id' => (int) $_POST['order_id'],
            ':order_number' => $snapshot['order_number'],
            ':invoice_number' => $_POST['invoice_number'],
            ':customer_id' => (int) $_POST['customer_id'],
            ':customer_name' => $snapshot['customer_name'],
            ':amount' => $_POST['amount'],
            ':status' => isset($_POST['status']) ? 1 : 0,
            ':id' => $_POST['id']
        ]);
        header("Location: index.php?Act=240");
        exit();
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM orderandinvoice WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $row = $stmt->fetch();
    if ($row === false) {
        echo "<p>" . t('invoice.edit.not_found') . "</p>";
        return;
    }

    $orders = $pdo->query("SELECT OrderID, TrackingNumber FROM Orders ORDER BY OrderID")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer ORDER BY CustomerID")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h3><?php echo t('invoice.edit.title'); ?></h3>
<form method="POST">
    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
    <label><?php echo t('invoice.edit.order_link'); ?></label>
    <select name="order_id" class="form-control" required>
        <?php foreach ($orders as $order): ?>
            <option value="<?php echo $order['OrderID']; ?>" <?php echo (int) $row['order_id'] === (int) $order['OrderID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($order['TrackingNumber'], ENT_QUOTES, 'UTF-8'); ?>（<?php echo $order['OrderID']; ?>）</option>
        <?php endforeach; ?>
    </select>
    <label><?php echo t('invoice.field.order_number_snapshot'); ?></label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars((string) $row['order_number'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
    <label><?php echo t('invoice.field.invoice_number'); ?></label>
    <input type="text" name="invoice_number" class="form-control" value="<?php echo htmlspecialchars($row['invoice_number'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <label><?php echo t('invoice.edit.customer_link'); ?></label>
    <select name="customer_id" class="form-control" required>
        <?php foreach ($customers as $customer): ?>
            <option value="<?php echo $customer['CustomerID']; ?>" <?php echo (int) $row['customer_id'] === (int) $customer['CustomerID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($customer['CustomerName'], ENT_QUOTES, 'UTF-8'); ?>（<?php echo $customer['CustomerID']; ?>）</option>
        <?php endforeach; ?>
    </select>
    <label><?php echo t('invoice.field.customer_name_snapshot'); ?></label>
    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
    <label><?php echo t('invoice.field.amount'); ?></label>
    <input type="number" name="amount" class="form-control" value="<?php echo htmlspecialchars((string) $row['amount'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <label><?php echo t('invoice.field.status'); ?></label>
    <input type="checkbox" name="status" <?php echo $row['status'] ? 'checked' : ''; ?>> <?php echo t('invoice.status.done'); ?>
    <br>
    <button type="submit" class="btn btn-primary"><?php echo t('common.update'); ?></button>
</form>
