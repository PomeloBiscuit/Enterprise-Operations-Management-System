<?php

/**
 * 依發票的兩個外鍵取得開立時應保存的快照。
 * 瀏覽器送來的名稱與編號不可信，快照只能由現行關聯資料導出。
 */
function get_invoice_snapshot(PDO $pdo, int $orderId, int $customerId): array
{
    $snapshotStmt = $pdo->prepare("
        SELECT o.OrderID AS order_number, c.CustomerName AS customer_name
        FROM Orders o CROSS JOIN Customer c
        WHERE o.OrderID = :order_id AND c.CustomerID = :customer_id
    ");
    $snapshotStmt->execute([
        ':order_id' => $orderId,
        ':customer_id' => $customerId,
    ]);
    $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);
    if ($snapshot === false) {
        throw new RuntimeException(t('invoice.add.err_not_found'));
    }

    return $snapshot;
}
