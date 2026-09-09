<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (can_access_self()) {
    $admid = $_SESSION['admid'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM User WHERE id = :id");
        $stmt->execute([':id' => $admid]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }
    if ($user) {
        $isAdmin = $user['limited'] == 1 ? t('profile.value.yes') : t('profile.value.no');
        $phone = $user['phone'];
        if (preg_match('/\((\d{2,4})\) (\d{4})-(\d{4})/', $phone, $matches)) {
            $phone = "({$matches[1]}) {$matches[2]}-{$matches[3]}";
        }
        $L_title = t('profile.view.title');
        $L_name = t('field.name');
        $L_account = t('field.account');
        $L_landline = t('profile.field.landline');
        $L_mobile = t('profile.field.mobile');
        $L_email = t('profile.field.email');
        $L_isAdmin = t('profile.field.is_admin');
        $L_lastMod = t('profile.field.last_modified');
        $L_created = t('profile.field.created');
        echo "
        <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
            <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>$L_title
                <a href='index.php?Act=105' style='float: right;'>
                    <i class='fas fa-cog'></i>
                </a>
            </h3><hr>
            <div class=\"table-responsive\">
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
                <tr>
                    <td>$L_name</td>
                    <td>{$user['name']}</td>
                </tr>
                <tr>
                    <td>$L_account</td>
                    <td>{$user['id']}</td>
                </tr>
                <tr>
                    <td>$L_landline</td>
                    <td>{$phone}</td>
                </tr>
                <tr>
                    <td>$L_mobile</td>
                    <td>{$user['phonem']}</td>
                </tr>
                <tr>
                    <td>$L_email</td>
                    <td>{$user['email']}</td>
                </tr>
                <tr>
                    <td>$L_isAdmin</td>
                    <td>$isAdmin</td>
                </tr>
                <tr>
                    <td>$L_lastMod</td>
                    <td>{$user['datechg']}</td>
                </tr>
                <tr>
                    <td>$L_created</td>
                    <td>{$user['dateadd']}</td>
                </tr>
            </table>
            </div>
        ";
        $L_delConfirm = t('profile.delete.confirm');
        $L_delAdminHint = t('profile.delete.admin_hint');
        $L_delButton = t('profile.delete.button');
        echo "
        <form method='post' action='index.php?Act=115' onsubmit='return confirm(\"$L_delConfirm\");' style='text-align: center;'>
            <input type='hidden' name='id' value='{$user['id']}'>
            <button type='submit' class='btn btn-danger' " . ($user['limited'] == 1 ? "disabled title=\"$L_delAdminHint\"" : '') . ">$L_delButton</button>
        </form>
        ";
        echo "</div>";
    } else {
        echo "<p>" . t('profile.not_found') . "</p>";
    }
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
