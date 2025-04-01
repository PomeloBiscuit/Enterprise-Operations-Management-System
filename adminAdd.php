<?php
ob_start(); // 新增：啟動緩衝區

$areaCodes = [
    '臺北' => '02',
    '桃園' => '03',
    '新竹' => '03',
    '花蓮' => '03',
    '宜蘭' => '03',
    '苗栗' => '037',
    '臺中' => '04',
    '彰化' => '04',
    '南投' => '049',
    '嘉義' => '05',
    '雲林' => '05',
    '臺南' => '06',
    '澎湖' => '06',
    '高雄' => '07',
    '屏東' => '08',
    '臺東' => '089',
    '金門' => '082',
    '烏坵' => '0826',
    '馬祖' => '0836'
];

if ($_SESSION["admlimit"] > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (
            empty($_POST['name']) ||
            empty($_POST['id']) ||
            empty($_POST['pwa']) ||
            $_POST['pwa'] !== $_POST['pwb']
        ) {
            $error = "資料未填完整或密碼不一致，請重新檢查！";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO User (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited)
                    VALUES (NOW(), NOW(), :name, :id, MD5(:pw), :phone, :phonem, :email, 1, 1, 1, 0)
                ");
                $phone = "({$_POST['phone_area']}) {$_POST['phone_main']}";
                $phonem = "09{$_POST['phonem1']}-{$_POST['phonem2']}-{$_POST['phonem3']}";
                $stmt->execute([
                    ':name' => $_POST['name'],
                    ':id' => $_POST['id'],
                    ':pw' => $_POST['pwa'],
                    ':phone' => $phone,
                    ':phonem' => $phonem,
                    ':email' => $_POST['email']
                ]);
                header("Location: index.php?Act=110&resultsPerPage=" . ($_POST['resultsPerPage'] ?? 10));
                exit();
            } catch (PDOException $e) {
                $error = "新增失敗：" . $e->getMessage();
            }
        }
    }
    if (!isset($error)) $error = "";
    // 只在此輸出表單與錯誤訊息
    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>新增使用者</h3><hr>
        <p style='color: red; text-align: center;'>$error</p>
        <form method='post' action='' onsubmit='return confirm(\"確認資料無誤？\");'>
            <input type='hidden' name='resultsPerPage' value='" . ($_GET['resultsPerPage'] ?? 10) . "'>
            <table class=\"table table-bordered table-hover\">
                <tr>
                    <td>UserID</td>
                    <td>";
                    try {
                        $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'User'");
                        $row = $stmt->fetch();
                        $nextUserID = $row['AUTO_INCREMENT'];
                        echo "<input type='text' class='form-control' value='$nextUserID' disabled>";
                    } catch (PDOException $e) {
                        echo "<p>無法取得UserID：" . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    echo "</td>
                </tr>
                <tr>
                    <td>姓名*</td>
                    <td><input type='text' name='name' class=\"form-control\" required></td>
                </tr>
                <tr>
                    <td>帳號*</td>
                    <td><input type='text' name='id' class=\"form-control\" required></td>
                </tr>
                <tr>
                    <td>密碼*</td>
                    <td>
                        <div class='input-group'>
                            <input type='password' name='pwa' id='pwa' class=\"form-control\" required>
                            <span class='input-group-text' onclick='togglePasswordVisibility(\"pwa\")'>
                                <i class='fas fa-eye-slash' id='togglePwa'></i>
                            </span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>確認密碼*</td>
                    <td>
                        <div class='input-group'>
                            <input type='password' name='pwb' id='pwb' class=\"form-control\" required>
                            <span class='input-group-text' onclick='togglePasswordVisibility(\"pwb\")'>
                                <i class='fas fa-eye-slash' id='togglePwb'></i>
                            </span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>固定電話</td>
                    <td>
                        <div class='input-group'>
                            <select id='areaSelect' class='form-select' required>
                                <option value=''>選擇地區</option>";
                                foreach ($areaCodes as $area => $code) {
                                    echo "<option value='$code'>$area</option>";
                                }
                                echo "
                            </select>
                            <span class='input-group-text'>(</span>
                            <input type='text' id='phoneArea' name='phone_area' class='form-control' pattern='\\d{2,4}' title='請輸入有效的區碼，2到4個數字' maxlength='4' placeholder='區碼' required>
                            <span class='input-group-text'>) </span>
                            <input type='text' name='phone_main' class='form-control' pattern='\\d{4}' title='請輸入有效的電話號碼，格式為 4個數字' maxlength='4' placeholder='1234' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phone_ext' class='form-control' pattern='\\d{4}' title='請輸入有效的電話號碼，格式為 4個數字' maxlength='4' placeholder='5678'>
                        </div>
                        <small class='form-text text-muted'>(格式: (區碼) 1234-5678)</small>
                    </td>
                </tr>
                <tr>
                    <td>行動電話</td>
                    <td>
                        <div class='input-group'>
                            <span class='input-group-text'>09</span>
                            <input type='text' name='phonem1' class='form-control' pattern='\\d{2}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='2' placeholder='12' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phonem2' class='form-control' pattern='\\d{3}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='3' placeholder='345' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phonem3' class='form-control' pattern='\\d{3}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='3' placeholder='678' required>
                        </div>
                        <small class='form-text text-muted'>(格式: 09xx-xxx-xxx)</small>
                    </td>
                </tr>
                <tr>
                    <td>電子郵件</td>
                    <td><input type='email' name='email' class=\"form-control\" required></td>
                </tr>
            </table>
            <div style='text-align: center;'>
                <a href='index.php?Act=110&resultsPerPage=" . ($_GET['resultsPerPage'] ?? 10) . "' class='btn btn-secondary' style='background-color: #6c757d; color: white;'>返回</a>
                <span style='display: inline-block; width: 20px;'></span>
                <input type='reset' value='清除' class=\"btn btn-warning\" style='background-color: #ffc107; color: white;'>
                <span style='display: inline-block; width: 20px;'></span>
                <input type='submit' name='btadd' value='新增' class=\"btn btn-primary\" style='background-color: #007bff; color: white;'>
            </div>
        </form>
    </div>
    ";
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>

<script>
document.getElementById('areaSelect').addEventListener('change', function() {
    document.getElementById('phoneArea').value = this.value;
});

document.getElementById('phoneArea').addEventListener('input', function() {
    const areaSelect = document.getElementById('areaSelect');
    const areaCodes = {
        '02': '臺北',
        '03': '桃園',
        '037': '苗栗',
        '04': '臺中',
        '049': '南投',
        '05': '嘉義',
        '06': '臺南',
        '07': '高雄',
        '08': '屏東',
        '089': '臺東',
        '082': '金門',
        '0826': '烏坵',
        '0836': '馬祖'
    };
    const value = this.value;
    areaSelect.value = Object.keys(areaCodes).find(key => key === value) || '';
});

function togglePasswordVisibility(id) {
    const input = document.getElementById(id);
    const icon = document.getElementById('toggle' + id.charAt(0).toUpperCase() + id.slice(1));
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fas', 'fa-eye-slash');
        icon.classList.add('fas', 'fa-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('fas', 'fa-eye');
        icon.classList.add('fas', 'fa-eye-slash');
    }
}
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
