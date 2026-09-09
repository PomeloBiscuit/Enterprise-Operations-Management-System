<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (can_manage_users()) {
    if (isset($_POST['selectedUsers'])) {
        $selectedUsers = $_POST['selectedUsers'];
        $resultsPerPage = $_POST['resultsPerPage'] ?? 10; // 預設顯示 10 筆
        try {
            $placeholders = rtrim(str_repeat('?,', count($selectedUsers)), ',');
            $stmt = $pdo->prepare("DELETE FROM User WHERE prikey IN ($placeholders)");
            $stmt->execute($selectedUsers);
            header("Location: index.php?Act=110&resultsPerPage=$resultsPerPage");
            exit();
        } catch (PDOException $e) {
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>" . t('user.none_selected') . "</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>
