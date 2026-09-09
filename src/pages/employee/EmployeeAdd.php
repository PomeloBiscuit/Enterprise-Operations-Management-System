<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 取得表單欄位
        $EmployeeName = $_POST['EmployeeName']; // 取得員工姓名
        $resultsPerPage = $_POST['resultsPerPage']; // 取得每頁顯示筆數

        // 執行資料庫 INSERT
        $stmt = $pdo->prepare("
            INSERT INTO Employee (EmployeeName)
            VALUES (:EmployeeName)
        ");
        $stmt->execute([ // 執行 SQL
            ':EmployeeName' => $EmployeeName // 綁定員工姓名
        ]);

        // 取得新增的 EmployeeID
        $employeeID = $pdo->lastInsertId(); // 取得最後一筆 AUTO_INCREMENT
        header("Location: index.php?Act=350&resultsPerPage=$resultsPerPage"); // 轉址回員工列表
        exit();
    } catch (Exception $e) { // 例外錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 資料庫錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }
}
?>

<div class="container mt-5">
    <div class="card" style="border-radius: 15px;"> <!-- 卡片 -->
        <div class="card-header text-center"> <!-- 卡片標題 -->
            <h3><?php echo t('employee.add.title'); ?></h3> <!-- 標題 -->
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 保留每頁顯示筆數 -->
                <div class="form-group">
                    <label><?php echo t('employee.field.id'); ?></label>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'Employee'), 0) + 1 AS AUTO_INCREMENT"); // SQLite：sqlite_sequence 取代 information_schema
                        $row = $stmt->fetch(); // 取得 AUTO_INCREMENT
                        $nextEmployeeID = $row['AUTO_INCREMENT']; // 取得 AUTO_INCREMENT
                        echo "<input type='text' class='form-control' value='$nextEmployeeID' disabled>"; // 顯示 AUTO_INCREMENT
                    } catch (PDOException $e) { // 資料庫錯誤
                        echo "<p>" . t('employee.add.id_error_prefix') . htmlspecialchars($e->getMessage()) . "</p>"; // 顯示錯誤訊息
                    }
                    ?>
                </div>
                <div class="form-group"> <!-- 表單群組 -->
                    <label><?php echo t('employee.field.name'); ?></label>
                    <input type="text" name="EmployeeName" class="form-control" placeholder="<?php echo htmlspecialchars(t('employee.add.name_ph')); ?>" required> <!-- 員工姓名 -->
                </div>
                <br>
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=350&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a> <!-- 返回 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning"><?php echo t('common.clear'); ?></button> <!-- 清除 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary"><?php echo t('common.submit'); ?></button> <!-- 送出 -->
                </div>
            </form>
        </div>
    </div>
</div>
