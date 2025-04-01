<?php
require_once("config.inc.php");

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
            <h3>新增員工</h3> <!-- 標題 -->
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 保留每頁顯示筆數 -->
                <div class="form-group"> 
                    <label>員工編號</label>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'Employee'"); // 取得 AUTO_INCREMENT
                        $row = $stmt->fetch(); // 取得 AUTO_INCREMENT
                        $nextEmployeeID = $row['AUTO_INCREMENT']; // 取得 AUTO_INCREMENT
                        echo "<input type='text' class='form-control' value='$nextEmployeeID' disabled>"; // 顯示 AUTO_INCREMENT
                    } catch (PDOException $e) { // 資料庫錯誤
                        echo "<p>無法取得員工編號：" . htmlspecialchars($e->getMessage()) . "</p>"; // 顯示錯誤訊息
                    }
                    ?>
                </div>
                <div class="form-group"> <!-- 表單群組 -->
                    <label>員工姓名</label>
                    <input type="text" name="EmployeeName" class="form-control" placeholder="請輸入員工姓名" required> <!-- 員工姓名 -->
                </div>
                <br>
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=350&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a> <!-- 返回 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning">清除</button> <!-- 清除 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary">送出</button> <!-- 送出 -->
                </div>
            </form>
        </div>
    </div>
</div>
