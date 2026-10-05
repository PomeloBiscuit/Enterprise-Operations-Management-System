<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

$resultsPerPage = intval($_POST['resultsPerPage'] ?? $_GET['resultsPerPage'] ?? 5);
$EmployeeID = $_POST['id'] ?? $_GET['id'] ?? '';
$row = null;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            'UPDATE Employee SET EmployeeName = :EmployeeName WHERE EmployeeID = :EmployeeID'
        );
        $stmt->execute([
            ':EmployeeName' => $_POST['EmployeeName'],
            ':EmployeeID' => $EmployeeID,
        ]);

        header("Location: index.php?Act=350&resultsPerPage=$resultsPerPage");
        exit();
    } catch (Throwable $e) {
        $errorMessage = user_safe_error($e);
    }
}

// POST 更新失敗後也重讀資料，讓使用者仍可看到可用表單。
$stmt = $pdo->prepare('SELECT * FROM Employee WHERE EmployeeID = :EmployeeID');
$stmt->execute([':EmployeeID' => $EmployeeID]);
$databaseRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($databaseRow !== false) {
    $row = array_map(static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $databaseRow);
}
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <?php if ($errorMessage !== ''): ?>
        <p><?php echo $errorMessage; ?></p>
    <?php endif; ?>
    <?php if ($row === null): ?>
        <p><?php echo t('common.record_not_found'); ?></p>
        <a href="index.php?Act=350&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
    <?php else: ?>
        <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;"><?php echo t('employee.edit.title'); ?></h3>
        <hr>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $row['EmployeeID']; ?>">
            <input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>">
            <div class="form-group">
                <label><?php echo t('employee.edit.id'); ?></label>
                <input type="text" name="EmployeeID" class="form-control" value="<?php echo $row['EmployeeID']; ?>" readonly>
            </div>
            <div class="form-group">
                <label><?php echo t('employee.field.name'); ?></label>
                <input type="text" name="EmployeeName" class="form-control" value="<?php echo $row['EmployeeName']; ?>" required>
            </div>
            <br>
            <div style="text-align: center;">
                <a href="index.php?Act=350&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
                <span style='display: inline-block; width: 20px;'></span>
                <button type="reset" class="btn btn-warning text-white"><?php echo t('common.clear'); ?></button>
                <span style='display: inline-block; width: 20px;'></span>
                <button type="submit" class="btn btn-success"><?php echo t('employee.edit.submit'); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
