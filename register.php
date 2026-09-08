<?php
require_once("config.inc.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $partyType = $_POST['party_type'] ?? '';
        if (!in_array($partyType, ['廠商', '客戶'], true)) {
            throw new InvalidArgumentException('請選擇身分。');
        }
        $stmt = $pdo->prepare("
            INSERT INTO User (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited, party_type)
            VALUES (datetime('now','localtime'), datetime('now','localtime'), :name, :id, :pw, '', '', :email, 1, 1, 1, 3, :party_type)
        ");
        $stmt->execute([
            ':name'  => $_POST['name'],
            ':id'    => $_POST['id'],
            ':pw'    => password_hash($_POST['pw'], PASSWORD_DEFAULT),
            ':email' => $_POST['email'],
            ':party_type' => $partyType
        ]);
        header("Location: index.php");
        exit();
    } catch (PDOException | InvalidArgumentException $e) {
        echo "<p>註冊失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<div class="container mt-5">
    <div class="card" style="border-radius: 15px;">
        <div class="card-header text-center">
            <h3>註冊帳號</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>姓名</label>
                    <input type="text" name="name" class="form-control" placeholder="請輸入您的姓名" required>
                </div>
                <div class="form-group">
                    <label>帳號</label>
                    <input type="text" name="id" class="form-control" placeholder="請輸入您的帳號" required>
                </div>
                <div class="form-group">
                    <label>密碼</label>
                    <input type="password" name="pw" class="form-control" placeholder="請輸入您的密碼" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Example@gmail.com" required>
                </div>
                <div class="form-group">
                    <label for="party_type">身分</label>
                    <select id="party_type" name="party_type" class="form-control" required>
                        <option value="" selected disabled>請選擇身分</option>
                        <option value="廠商">廠商</option>
                        <option value="客戶">客戶</option>
                    </select>
                </div>
                <br>
                <div class="text-center">
                    <a href="index.php" class="btn btn-secondary">返回</a>
                    <span style='display: inline-block; width: 20px;'></span>
                    <button type="reset" class="btn btn-warning">清除</button>
                    <span style='display: inline-block; width: 20px;'></span>
                    <button type="submit" class="btn btn-primary">送出</button>
                </div>
            </form>
        </div>
    </div>
</div>
