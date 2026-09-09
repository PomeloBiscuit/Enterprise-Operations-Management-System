<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_manage_users()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}
if (can_manage_users()) {
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
            $L_editTitle = t('user.edit.title');
            $L_userId = t('user.edit.field_userid');
            $L_name = t('field.name');
            $L_account = t('field.account');
            $L_password = t('field.password');
            $L_phone = t('field.phone');
            $L_mobile = t('user.field.mobile');
            $L_email = t('user.field.email');
            $L_back = t('common.back');
            $L_clear = t('common.clear');
            $L_edit = t('user.action.edit');
            $L_loading = t('user.edit.loading');
            echo "
            <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
                <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>$L_editTitle</h3><hr>
                <form method='post' action='index.php?Act=$Act&EK=$EK' onsubmit='showLoadingMessage()'>
                    <input type='hidden' name='resultsPerPage' value='{$resultsPerPage}'>
                    <div class=\"table-responsive\">
                    <table class=\"table table-bordered table-hover\">
                        <tr>
                            <td>$L_userId</td>
                            <td><input type='text' name='prikey' value='{$row['prikey']}' class=\"form-control\" readonly></td>
                        </tr>
                        <tr>
                            <td>$L_name*</td>
                            <td><input type='text' name='name' value='{$row['name']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>$L_account*</td>
                            <td><input type='text' name='id' value='{$row['id']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>$L_password*</td>
                            <td><input type='password' name='pwa' value='******' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>$L_phone</td>
                            <td><input type='text' name='phone' value='{$row['phone']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>$L_mobile</td>
                            <td><input type='text' name='phonem' value='{$row['phonem']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td>$L_email</td>
                            <td><input type='text' name='email' value='{$row['email']}' class=\"form-control\"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <div style='text-align: center;'>
                                    <a href='index.php?Act=110&resultsPerPage={$resultsPerPage}' class='btn btn-secondary' style='background-color: #6c757d; color: white;'>$L_back</a>
                                    <span style='display: inline-block; width: 20px;'></span>
                                    <input type='reset' value='$L_clear' class=\"btn btn-danger\" style='background-color: #ff6f61; color: white;'>
                                    <span style='display: inline-block; width: 20px;'></span>
                                    <input type='submit' name='btadd' value='$L_edit' class=\"btn btn-success\" style='background-color: #77dd77; color: white;'>
                                </div>
                            </td>
                        </tr>
                    </table>
                    </div>
                </form>
                <div id='loadingMessage' style='display: none; text-align: center; color: green;'>$L_loading</div>
            </div>
            ";
        }
    } else {
        try {
            // 只有輸入了新密碼（且不是表單預設的 ****** 遮罩）才更新 pw 欄位，
            // 否則沿用原本的雜湊 —— 避免每次編輯都把密碼重設掉。
            $newPw = $_POST['pwa'] ?? '';
            $updatePw = ($newPw !== '' && $newPw !== '******');

            $sql = "UPDATE User SET
                datechg=datetime('now','localtime'),
                name=:name,
                id=:id,"
                . ($updatePw ? "\n                pw=:pwa," : "") . "
                phone=:phone,
                phonem=:phonem,
                email=:email
                WHERE prikey=:prikey";
            $params = [
                ':name' => $_POST['name'],
                ':id' => $_POST['id'],
                ':phone' => $_POST['phone'],
                ':phonem' => $_POST['phonem'],
                ':email' => $_POST['email'],
                ':prikey' => $EK
            ];
            if ($updatePw) {
                $params[':pwa'] = password_hash($newPw, PASSWORD_DEFAULT);
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            echo "<p>Error updating admin: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        header("Location: index.php?Act=110&resultsPerPage={$resultsPerPage}");
        exit();
    }
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>

<script>
function showLoadingMessage() {
    document.getElementById('loadingMessage').style.display = 'block';
}
</script>
