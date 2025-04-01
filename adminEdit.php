<?php
if ($_SESSION["admlimit"] > 0) {
    $EK = intval($_GET['EK']);
    $resultsPerPage = $_POST['resultsPerPage'] ?? $_GET['resultsPerPage'] ?? 5;
    if (empty($_POST['btadd'])) {
        try {
            $sql = "SELECT * FROM User WHERE prikey='{$EK}' AND enabled > 0 ORDER BY name";
            $result = $pdo->query($sql);
        } catch (PDOException $e) {
            echo "<p>Error fetching admin: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        if ($row = $result->fetch()) {
            echo "
            <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
                <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>修改使用者</h3><hr>
                <form method='post' action='index.php?Act=$Act&EK=$EK' onsubmit='showLoadingMessage()'>
                    <input type='hidden' name='resultsPerPage' value='{$resultsPerPage}'>
                    <table class=\"table table-bordered table-hover\">
                        <tr>
                            <td>使用者ID</td>
                            <td><input type='text' name='prikey' value='{$row['prikey']}' class=\"form-control\" readonly></td>
                        </tr>
                        <tr>
                            <td>姓名*</td>
                            <td><input type='text' name='name' value='{$row['name']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>帳號*</td>
                            <td><input type='text' name='id' value='{$row['id']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>密碼*</td>
                            <td><input type='password' name='pwa' value='******' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>電話</td>
                            <td><input type='text' name='phone' value='{$row['phone']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>行動電話</td>
                            <td><input type='text' name='phonem' value='{$row['phonem']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>電子郵件</td>
                            <td><input type='text' name='email' value='{$row['email']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <div style='text-align: center;'>
                                    <a href='index.php?Act=110&resultsPerPage={$resultsPerPage}' class='btn btn-secondary' style='background-color: #6c757d; color: white;'>返回</a>
                                    <span style='display: inline-block; width: 20px;'></span>
                                    <input type='reset' value='清除' class=\"btn btn-danger\" style='background-color: #ff6f61; color: white;'>
                                    <span style='display: inline-block; width: 20px;'></span>
                                    <input type='submit' name='btadd' value='修改' class=\"btn btn-success\" style='background-color: #77dd77; color: white;'>
                                </div>
                            </td>
                        </tr>
                    </table>
                </form>
                <div id='loadingMessage' style='display: none; text-align: center; color: green;'>資料更新中，請稍候...</div>
            </div>
            ";
        }
    } else {
        try {
            $sql = "UPDATE User SET
                datechg=NOW(),
                name=:name,
                id=:id,
                pw=MD5(:pwa),
                phone=:phone,
                phonem=:phonem,
                email=:email
                WHERE prikey=:prikey";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $_POST['name'],
                ':id' => $_POST['id'],
                ':pwa' => $_POST['pwa'],
                ':phone' => $_POST['phone'],
                ':phonem' => $_POST['phonem'],
                ':email' => $_POST['email'],
                ':prikey' => $EK
            ]);
        } catch (PDOException $e) {
            echo "<p>Error updating admin: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        header("Location: index.php?Act=110&resultsPerPage={$resultsPerPage}");
        exit();
    }
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>

<script>
function showLoadingMessage() {
    document.getElementById('loadingMessage').style.display = 'block';
}
</script>

