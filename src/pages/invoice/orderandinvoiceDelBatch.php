<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (can_view_business_data()) {
    if (isset($_POST['selectedOrders'])) {
        $selectedOrders = $_POST['selectedOrders'];
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedOrders)), ',');
            $stmt = $pdo->prepare("DELETE FROM orderandinvoice WHERE id IN ($placeholders)");
            $stmt->execute($selectedOrders);
            header("Location: index.php?Act=240");
            exit();
        } catch (PDOException $e) {
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>" . t('invoice.none_selected') . "</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>
