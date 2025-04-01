<?php
require_once("config.inc.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['admid'])) {
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
                echo "<p>管理員帳號無法刪除。</p>";
            }
        } catch (PDOException $e) {
            echo "<p>刪除失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        header("Location: login.php");
        exit();
    }
}
?>
