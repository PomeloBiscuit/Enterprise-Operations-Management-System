<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . '</p>';
    exit;
}

$EK = (int) ($_POST['EK'] ?? $_GET['EK'] ?? 0);
$row = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            'UPDATE admin SET fc = :fc, fcname = :fcname, fcaddress = :fcaddress, fcphone = :fcphone, fcphonem = :fcphonem, fcemail = :fcemail, fcid = :fcid WHERE prikey = :prikey'
        );
        $stmt->execute([
            ':fc' => $_POST['fc'],
            ':fcname' => $_POST['fcname'],
            ':fcaddress' => $_POST['fcaddress'],
            ':fcphone' => $_POST['fcphone'],
            ':fcphonem' => $_POST['fcphonem'],
            ':fcemail' => $_POST['fcemail'],
            ':fcid' => $_POST['fcid'],
            ':prikey' => $EK,
        ]);
        header('Location: index.php?Act=200');
        exit();
    } catch (Throwable $e) {
        $errorMessage = user_safe_error($e);
    }
}

// POST 更新失敗後重讀原始資料；不存在時不畫表單。
$stmt = $pdo->prepare('SELECT * FROM admin WHERE prikey = :prikey AND enabled > 0 ORDER BY fcname');
$stmt->execute([':prikey' => $EK]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php if ($errorMessage !== ''): ?>
    <p><?php echo $errorMessage; ?></p>
<?php endif; ?>
<?php if ($row === false): ?>
    <p><?php echo t('common.record_not_found'); ?></p>
    <a href="index.php?Act=200" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
<?php else: ?>
    <form method="post" action="index.php?Act=230&amp;EK=<?php echo $EK; ?>">
        <input type="hidden" name="EK" value="<?php echo $EK; ?>">
        <h3><?php echo t('firm.edit.title'); ?></h3>
        <h5><?php echo t('firm.notice.blank_page'); ?></h5>
        <hr>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <tr><td><?php echo t('firm.field.fc'); ?>*</td><td><input type="text" name="fc" value="<?php echo htmlspecialchars((string) $row['fc'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('field.name'); ?>*</td><td><input type="text" name="fcname" value="<?php echo htmlspecialchars((string) $row['fcname'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('field.address'); ?>*</td><td><input type="text" name="fcaddress" value="<?php echo htmlspecialchars((string) $row['fcaddress'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('field.phone'); ?></td><td><input type="text" name="fcphone" value="<?php echo htmlspecialchars((string) $row['fcphone'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('field.mobile'); ?></td><td><input type="text" name="fcphonem" value="<?php echo htmlspecialchars((string) $row['fcphonem'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('firm.field.email'); ?></td><td><input type="text" name="fcemail" value="<?php echo htmlspecialchars((string) $row['fcemail'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td><?php echo t('firm.field.id'); ?></td><td><input type="text" name="fcid" value="<?php echo htmlspecialchars((string) $row['fcid'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control"></td></tr>
                <tr><td></td><td><button type="submit" name="btadd" value="1" class="btn btn-default"><?php echo t('firm.action.edit'); ?></button> <button type="reset" class="btn btn-default"><?php echo t('common.clear'); ?></button></td></tr>
            </table>
        </div>
    </form>
<?php endif; ?>
