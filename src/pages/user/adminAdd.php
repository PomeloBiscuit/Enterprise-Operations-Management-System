<?php
ob_start(); // 新增：啟動緩衝區
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_manage_users()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

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

// 縣市顯示文字在英文模式改用羅馬拼音（多個縣市共用同一區碼，無法用區碼當翻譯鍵，
// 故比照 profileEdit.php 在頁內維護對照表）。
$areaLabels = [
    '臺北' => 'Taipei', '桃園' => 'Taoyuan', '新竹' => 'Hsinchu', '花蓮' => 'Hualien',
    '宜蘭' => 'Yilan', '苗栗' => 'Miaoli', '臺中' => 'Taichung', '彰化' => 'Changhua',
    '南投' => 'Nantou', '嘉義' => 'Chiayi', '雲林' => 'Yunlin', '臺南' => 'Tainan',
    '澎湖' => 'Penghu', '高雄' => 'Kaohsiung', '屏東' => 'Pingtung', '臺東' => 'Taitung',
    '金門' => 'Kinmen', '烏坵' => 'Wuqiu', '馬祖' => 'Matsu',
];

if (can_manage_users()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (
            empty($_POST['name']) ||
            empty($_POST['id']) ||
            empty($_POST['pwa']) ||
            $_POST['pwa'] !== $_POST['pwb']
        ) {
            $error = t('user.add.err_incomplete');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO User (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited)
                    VALUES (datetime('now','localtime'), datetime('now','localtime'), :name, :id, :pw, :phone, :phonem, :email, 1, 1, 1, 0)
                ");
                $phone = "({$_POST['phone_area']}) {$_POST['phone_main']}";
                $phonem = "09{$_POST['phonem1']}-{$_POST['phonem2']}-{$_POST['phonem3']}";
                $stmt->execute([
                    ':name' => $_POST['name'],
                    ':id' => $_POST['id'],
                    ':pw' => password_hash($_POST['pwa'], PASSWORD_DEFAULT),
                    ':phone' => $phone,
                    ':phonem' => $phonem,
                    ':email' => $_POST['email']
                ]);
                header("Location: index.php?Act=110&resultsPerPage=" . ($_POST['resultsPerPage'] ?? 10));
                exit();
            } catch (PDOException $e) {
                $error = t('user.add.err_prefix') . $e->getMessage();
            }
        }
    }
    if (!isset($error)) $error = "";
    $L_addTitle = t('user.add.title');
    $L_confirmSubmit = json_encode(t('user.add.confirm_submit'));
    $L_name = t('field.name');
    $L_account = t('field.account');
    $L_password = t('field.password');
    $L_confirmPassword = t('user.add.confirm_password');
    $L_landline = t('user.field.landline');
    $L_mobile = t('user.field.mobile');
    $L_email = t('user.field.email');
    $L_selectArea = t('common.select_area');
    $L_areaHint = t('user.add.area_hint');
    $L_areaPh = t('user.add.area_ph');
    $L_phone4Hint = t('user.add.phone4_hint');
    $L_mobileHint = t('user.add.mobile_hint');
    $L_landlineFormat = t('user.add.landline_format');
    $L_mobileFormat = t('user.add.mobile_format');
    $L_back = t('common.back');
    $L_clear = t('common.clear');
    $L_add = t('common.add');
    // 只在此輸出表單與錯誤訊息
    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>$L_addTitle</h3><hr>
        <p style='color: red; text-align: center;'>$error</p>
        <form method='post' action='' onsubmit='return confirm($L_confirmSubmit);'>
            <input type='hidden' name='resultsPerPage' value='" . ($_GET['resultsPerPage'] ?? 10) . "'>
            <table class=\"table table-bordered table-hover\">
                <tr>
                    <td>UserID</td>
                    <td>";
                    try {
                        // SQLite 沒有 information_schema。等價作法：讀 sqlite_sequence 的 seq + 1；
                        // 表還沒插過資料時 sqlite_sequence 無該列，用 COALESCE 補 0 → 顯示 1。
                        $stmt = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'User'), 0) + 1 AS AUTO_INCREMENT");
                        $row = $stmt->fetch();
                        $nextUserID = $row['AUTO_INCREMENT'];
                        echo "<input type='text' class='form-control' value='$nextUserID' disabled>";
                    } catch (PDOException $e) {
                        echo "<p>" . t('user.add.userid_error_prefix') . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    echo "</td>
                </tr>
                <tr>
                    <td>$L_name*</td>
                    <td><input type='text' name='name' class=\"form-control\" required></td>
                </tr>
                <tr>
                    <td>$L_account*</td>
                    <td><input type='text' name='id' class=\"form-control\" required></td>
                </tr>
                <tr>
                    <td>$L_password*</td>
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
                    <td>$L_confirmPassword*</td>
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
                    <td>$L_landline</td>
                    <td>
                        <div class='input-group'>
                            <select id='areaSelect' class='form-select' required>
                                <option value=''>$L_selectArea</option>";
                                $isEn = current_locale() === 'en';
                                foreach ($areaCodes as $area => $code) {
                                    $areaText = $isEn ? ($areaLabels[$area] ?? $area) : $area;
                                    echo "<option value='$code'>$areaText</option>";
                                }
                                echo "
                            </select>
                            <span class='input-group-text'>(</span>
                            <input type='text' id='phoneArea' name='phone_area' class='form-control' pattern='\\d{2,4}' title='$L_areaHint' maxlength='4' placeholder='$L_areaPh' required>
                            <span class='input-group-text'>) </span>
                            <input type='text' name='phone_main' class='form-control' pattern='\\d{4}' title='$L_phone4Hint' maxlength='4' placeholder='1234' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phone_ext' class='form-control' pattern='\\d{4}' title='$L_phone4Hint' maxlength='4' placeholder='5678'>
                        </div>
                        <small class='form-text text-muted'>$L_landlineFormat</small>
                    </td>
                </tr>
                <tr>
                    <td>$L_mobile</td>
                    <td>
                        <div class='input-group'>
                            <span class='input-group-text'>09</span>
                            <input type='text' name='phonem1' class='form-control' pattern='\\d{2}' title='$L_mobileHint' maxlength='2' placeholder='12' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phonem2' class='form-control' pattern='\\d{3}' title='$L_mobileHint' maxlength='3' placeholder='345' required>
                            <span class='input-group-text'>-</span>
                            <input type='text' name='phonem3' class='form-control' pattern='\\d{3}' title='$L_mobileHint' maxlength='3' placeholder='678' required>
                        </div>
                        <small class='form-text text-muted'>$L_mobileFormat</small>
                    </td>
                </tr>
                <tr>
                    <td>$L_email</td>
                    <td><input type='email' name='email' class=\"form-control\" required></td>
                </tr>
            </table>
            <div style='text-align: center;'>
                <a href='index.php?Act=110&resultsPerPage=" . ($_GET['resultsPerPage'] ?? 10) . "' class='btn btn-secondary' style='background-color: #6c757d; color: white;'>$L_back</a>
                <span style='display: inline-block; width: 20px;'></span>
                <input type='reset' value='$L_clear' class=\"btn btn-warning\" style='background-color: #ffc107; color: white;'>
                <span style='display: inline-block; width: 20px;'></span>
                <input type='submit' name='btadd' value='$L_add' class=\"btn btn-primary\" style='background-color: #007bff; color: white;'>
            </div>
        </form>
    </div>
    ";
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
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
