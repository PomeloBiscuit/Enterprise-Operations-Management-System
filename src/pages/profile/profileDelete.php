<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_access_self()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admid = $_SESSION['admid'];

    try {
        // 確認當前登入的帳號不是管理員
        $stmt = $pdo->prepare("SELECT limited FROM User WHERE id = :id");
        $stmt->execute([':id' => $admid]);
        $user = $stmt->fetch();

        if ($user && $user['limited'] != 1) {
            $stmt = $pdo->prepare("UPDATE User SET enabled = 0 WHERE id = :id");
            $stmt->execute([':id' => $admid]);

            // 刪除成功後登出並跳轉到登入畫面
            session_unset();
            session_destroy();

            header("Location: login.php");
            exit();
        } else {
            echo "<p>" . t('profile.delete.admin_only_msg') . "</p>";
        }
    } catch (PDOException $e) {
        echo "<p>" . t('common.delete_fail_prefix') . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
