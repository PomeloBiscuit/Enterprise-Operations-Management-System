<?php
if ($_SESSION["admlimit"] > 0) {
    $admid = $_SESSION['admid'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM User WHERE id = :id");
        $stmt->execute([':id' => $admid]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
    if ($user) {
        $isAdmin = $user['limited'] == 1 ? '是' : '否';
        $phone = $user['phone'];
        if (preg_match('/\((\d{2,4})\) (\d{4})-(\d{4})/', $phone, $matches)) {
            $phone = "({$matches[1]}) {$matches[2]}-{$matches[3]}";
        }
        echo "
        <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
            <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>個人資料
                <a href='index.php?Act=105' style='float: right;'>
                    <i class='fas fa-cog'></i>
                </a>
            </h3><hr>
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
                <tr>
                    <td>姓名</td>
                    <td>{$user['name']}</td>
                </tr>
                <tr>
                    <td>帳號</td>
                    <td>{$user['id']}</td>
                </tr>
                <tr>
                    <td>固定電話</td>
                    <td>{$phone}</td>
                </tr>
                <tr>
                    <td>行動電話</td>
                    <td>{$user['phonem']}</td>
                </tr>
                <tr>
                    <td>電子郵件</td>
                    <td>{$user['email']}</td>
                </tr>
                <tr>
                    <td>是否為管理員</td>
                    <td>$isAdmin</td>
                </tr>
                <tr>
                    <td>最後修改日期</td>
                    <td>{$user['datechg']}</td>
                </tr>
                <tr>
                    <td>新增日期</td>
                    <td>{$user['dateadd']}</td>
                </tr>
            </table>
        ";
        echo "
        <form method='post' action='index.php?Act=115' onsubmit='return confirm(\"確定要刪除您的個人資料嗎？\");' style='text-align: center;'>
            <input type='hidden' name='id' value='{$user['id']}'>
            <button type='submit' class='btn btn-danger' " . ($user['limited'] == 1 ? 'disabled title=\"管理員無法刪除帳號\"' : '') . ">刪除個人資料</button>
        </form>
        ";
        echo "</div>";
    } else {
        echo "<p>找不到使用者資料。</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
