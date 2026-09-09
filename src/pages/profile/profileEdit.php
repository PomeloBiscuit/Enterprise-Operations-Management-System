<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_access_self()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}
if (can_access_self()) {
    $admid = $_SESSION['admid'];
    // 縣市代碼。顯示文字在英文模式改用羅馬拼音（多個縣市共用同一區碼，
    // 無法用區碼當翻譯鍵，故在頁內維護對照表）。
    $areaLabels = [
        '臺北' => 'Taipei', '桃園' => 'Taoyuan', '新竹' => 'Hsinchu', '花蓮' => 'Hualien',
        '宜蘭' => 'Yilan', '苗栗' => 'Miaoli', '臺中' => 'Taichung', '彰化' => 'Changhua',
        '南投' => 'Nantou', '嘉義' => 'Chiayi', '雲林' => 'Yunlin', '臺南' => 'Tainan',
        '澎湖' => 'Penghu', '高雄' => 'Kaohsiung', '屏東' => 'Pingtung', '臺東' => 'Taitung',
        '金門' => 'Kinmen', '烏坵' => 'Wuqiu', '馬祖' => 'Matsu',
    ];
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $stmt = $pdo->prepare("
                UPDATE User
                SET name = :name,
                    phone = :phone,
                    phonem = :phonem,
                    email = :email
                WHERE id = :id
            ");
            $phone = "({$_POST['phone_area']}) {$_POST['phone_main']}" . (!empty($_POST['phone_ext']) ? "-{$_POST['phone_ext']}" : "");
            $phonem = "09{$_POST['phonem1']}-{$_POST['phonem2']}-{$_POST['phonem3']}";
            $stmt->execute([
                ':name' => $_POST['name'],
                ':phone' => $phone,
                ':phonem' => $phonem,
                ':email' => $_POST['email'],
                ':id' => $admid
            ]);
            header("Location: index.php?Act=100");
            exit();
        } catch (PDOException $e) {
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
        }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE id = :id");
            $stmt->execute([':id' => $admid]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
        }
    }
    if ($user) {
        $phone_parts = explode(' ', str_replace(['(', ')'], '', $user['phone']));
        $phone_area = $phone_parts[0] ?? '';
        $phone_main_parts = explode('-', $phone_parts[1] ?? '');
        $phone_main = $phone_main_parts[0] ?? '';
        $phone_ext = $phone_main_parts[1] ?? '';
        $phonem_parts = explode('-', str_replace('09', '', $user['phonem']));
        $phonem1 = $phonem_parts[0] ?? '';
        $phonem2 = $phonem_parts[1] ?? '';
        $phonem3 = $phonem_parts[2] ?? '';
        $L_title = t('profile.edit.title');
        $L_name = t('field.name');
        $L_landline = t('profile.field.landline');
        $L_selectArea = t('common.select_area');
        $L_areaHint = t('profile.edit.area_hint');
        $L_phone3Hint = t('profile.edit.phone3_hint');
        $L_phone4Hint = t('profile.edit.phone4_hint');
        $L_landlineFormat = t('profile.edit.landline_format');
        $L_mobile = t('profile.field.mobile');
        $L_mobileHint = t('profile.edit.mobile_hint');
        $L_mobileFormat = t('profile.edit.mobile_format');
        $L_email = t('profile.field.email');
        $L_cancel = t('common.cancel');
        $L_clear = t('common.clear');
        $L_update = t('common.update');
        $isEn = current_locale() === 'en';
        echo "
        <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
            <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>$L_title</h3><hr>
            <form method='post' action=''>
                <div class=\"table-responsive\">
                <table class=\"table table-bordered table-hover\" style='width: 100%;'>
                    <tr>
                        <td>$L_name</td>
                        <td><input type='text' name='name' class='form-control' value='{$user['name']}' required></td>
                    </tr>
                    <tr>
                        <td>$L_landline</td>
                        <td>
                            <div class='input-group'>
                                <select id='areaSelect' name='phone_area' class='form-select' required>
                                    <option value=''>$L_selectArea</option>";
                                    foreach ($areaCodes as $area => $code) {
                                        $selected = $code === $phone_area ? 'selected' : '';
                                        $areaText = $isEn ? ($areaLabels[$area] ?? $area) : $area;
                                        echo "<option value='$code' $selected>$areaText</option>";
                                    }
                                    echo "
                                </select>
                                <span class='input-group-text'>(</span>
                                <input type='text' id='phoneArea' name='phone_area' class='form-control' pattern='\\d{2,4}' title='$L_areaHint' maxlength='4' value='$phone_area' required>
                                <span class='input-group-text'>) </span>
                                <input type='text' name='phone_main' class='form-control' pattern='\\d{3}' title='$L_phone3Hint' maxlength='3' value='$phone_main' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phone_ext' class='form-control' pattern='\\d{4}' title='$L_phone4Hint' maxlength='4' value='$phone_ext'>
                            </div>
                            <small class='form-text text-muted'>$L_landlineFormat</small>
                        </td>
                    </tr>
                    <tr>
                        <td>$L_mobile</td>
                        <td>
                            <div class='input-group'>
                                <span class='input-group-text'>09</span>
                                <input type='text' name='phonem1' class='form-control' pattern='\\d{2}' title='$L_mobileHint' maxlength='2' value='$phonem1' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phonem2' class='form-control' pattern='\\d{3}' title='$L_mobileHint' maxlength='3' value='$phonem2' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phonem3' class='form-control' pattern='\\d{3}' title='$L_mobileHint' maxlength='3' value='$phonem3' required>
                            </div>
                            <small class='form-text text-muted'>$L_mobileFormat</small>
                        </td>
                    </tr>
                    <tr>
                        <td>$L_email</td>
                        <td><input type='email' name='email' class='form-control' value='{$user['email']}' required></td>
                    </tr>
                </table>
                </div>
                <div style='text-align: center;'>
                    <a href='index.php?Act=100' class='btn btn-secondary'>$L_cancel</a>
                    <span style='display: inline-block; width: 20px;'></span>
                    <input type='reset' value='$L_clear' class='btn btn-warning'>
                    <span style='display: inline-block; width: 20px;'></span>
                    <input type='submit' value='$L_update' class='btn btn-primary'>
                </div>
            </form>
        </div>
        ";
    } else {
        echo "<p>" . t('profile.not_found') . "</p>";
    }
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
</script>
