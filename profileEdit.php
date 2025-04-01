<?php
if ($_SESSION["admlimit"] > 0) {
    $admid = $_SESSION['admid'];
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
            echo "<p>錯誤：" . $e->getMessage() . "</p>";
        }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE id = :id");
            $stmt->execute([':id' => $admid]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            echo "<p>錯誤：" . $e->getMessage() . "</p>";
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
        echo "
        <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
            <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>修改個人資料</h3><hr>
            <form method='post' action=''>
                <table class=\"table table-bordered table-hover\" style='width: 100%;'>
                    <tr>
                        <td>姓名</td>
                        <td><input type='text' name='name' class='form-control' value='{$user['name']}' required></td>
                    </tr>
                    <tr>
                        <td>固定電話</td>
                        <td>
                            <div class='input-group'>
                                <select id='areaSelect' name='phone_area' class='form-select' required>
                                    <option value=''>選擇地區</option>";
                                    foreach ($areaCodes as $area => $code) {
                                        $selected = $code === $phone_area ? 'selected' : '';
                                        echo "<option value='$code' $selected>$area</option>";
                                    }
                                    echo "
                                </select>
                                <span class='input-group-text'>(</span>
                                <input type='text' id='phoneArea' name='phone_area' class='form-control' pattern='\\d{2,4}' title='請輸入有效的區碼，2到4個數字' maxlength='4' value='$phone_area' required>
                                <span class='input-group-text'>) </span>
                                <input type='text' name='phone_main' class='form-control' pattern='\\d{3}' title='請輸入有效的電話號碼，格式為 3個數字' maxlength='3' value='$phone_main' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phone_ext' class='form-control' pattern='\\d{4}' title='請輸入有效的電話號碼，格式為 4個數字' maxlength='4' value='$phone_ext'>
                            </div>
                            <small class='form-text text-muted'>(格式: (區碼) 123-4567)</small>
                        </td>
                    </tr>
                    <tr>
                        <td>行動電話</td>
                        <td>
                            <div class='input-group'>
                                <span class='input-group-text'>09</span>
                                <input type='text' name='phonem1' class='form-control' pattern='\\d{2}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='2' value='$phonem1' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phonem2' class='form-control' pattern='\\d{3}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='3' value='$phonem2' required>
                                <span class='input-group-text'>-</span>
                                <input type='text' name='phonem3' class='form-control' pattern='\\d{3}' title='請輸入有效的行動電話號碼，格式為 09xx-xxx-xxx' maxlength='3' value='$phonem3' required>
                            </div>
                            <small class='form-text text-muted'>(格式: 09xx-xxx-xxx)</small>
                        </td>
                    </tr>
                    <tr>
                        <td>電子郵件</td>
                        <td><input type='email' name='email' class='form-control' value='{$user['email']}' required></td>
                    </tr>
                </table>
                <div style='text-align: center;'>
                    <a href='index.php?Act=100' class='btn btn-secondary'>取消</a>
                    <span style='display: inline-block; width: 20px;'></span>
                    <input type='reset' value='清除' class='btn btn-warning'>
                    <span style='display: inline-block; width: 20px;'></span>
                    <input type='submit' value='更新' class='btn btn-primary'>
                </div>
            </form>
        </div>
        ";
    } else {
        echo "<p>找不到使用者資料。</p>";
    }
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
</script>
