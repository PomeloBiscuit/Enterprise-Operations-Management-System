<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

$resultsPerPage = intval($_POST['resultsPerPage'] ?? $_GET['resultsPerPage'] ?? 5);
$ProductID = $_POST['id'] ?? $_GET['id'] ?? '';
$row = null;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            'UPDATE Product SET ProductName = :ProductName, ProductCategory = :ProductCategory, UnitPrice = :UnitPrice WHERE ProductID = :ProductID'
        );
        $stmt->execute([
            ':ProductName' => $_POST['ProductName'],
            ':ProductCategory' => $_POST['ProductCategory'],
            ':UnitPrice' => $_POST['UnitPrice'],
            ':ProductID' => $ProductID,
        ]);

        header("Location: index.php?Act=390&resultsPerPage=$resultsPerPage");
        exit();
    } catch (Throwable $e) {
        $errorMessage = user_safe_error($e);
    }
}

// POST 更新失敗後也重讀資料，找不到時改顯示提示而不是畫壞表單。
$stmt = $pdo->prepare('SELECT * FROM Product WHERE ProductID = :ProductID');
$stmt->execute([':ProductID' => $ProductID]);
$databaseRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($databaseRow !== false) {
    $row = array_map(static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $databaseRow);
}
?>

<div class="container mt-5">
    <?php if ($errorMessage !== ''): ?>
        <p><?php echo $errorMessage; ?></p>
    <?php endif; ?>
    <?php if ($row === null): ?>
        <p><?php echo t('common.record_not_found'); ?></p>
        <a href="index.php?Act=390&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
    <?php else: ?>
        <div class="card" style="border-radius: 15px;">
            <div class="card-header text-center">
                <h3><?php echo t('product.edit.title'); ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>">
                    <div class="form-group">
                        <label><?php echo t('product.field.id'); ?></label>
                        <input type="text" class="form-control" value="<?php echo $row['ProductID']; ?>" disabled>
                        <input type="hidden" name="id" value="<?php echo $row['ProductID']; ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('product.field.name'); ?></label>
                        <input type="text" name="ProductName" class="form-control" value="<?php echo $row['ProductName']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('product.field.category'); ?></label>
                        <input type="text" name="ProductCategory" class="form-control" value="<?php echo $row['ProductCategory']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('product.field.unit_price'); ?></label>
                        <input type="number" step="0.01" name="UnitPrice" class="form-control" value="<?php echo $row['UnitPrice']; ?>" required>
                    </div>
                    <br>
                    <div class="text-center">
                        <a href="index.php?Act=390&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="reset" class="btn btn-warning"><?php echo t('common.clear'); ?></button>
                        <span style='display: inline-block; width: 20px;'></span>
                        <button type="submit" class="btn btn-primary"><?php echo t('common.update'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
