<?php
require_once("config.inc.php"); // 引入資料庫設定

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 判斷是否為 POST 方法
    try {
        // 取得表單欄位
        $EmployeeID = $_POST['id']; // 取得員工 ID
        $EmployeeName = $_POST['EmployeeName']; // 取得員工姓名
        $resultsPerPage = $_POST['resultsPerPage']; // 取得每頁顯示筆數

        // 執行資料庫 UPDATE
        $stmt = $pdo->prepare("
            UPDATE Employee
            SET EmployeeName = :EmployeeName
            WHERE EmployeeID = :EmployeeID 
        "); // 準備 SQL
        $stmt->execute([ 
            ':EmployeeName' => $EmployeeName,
            ':EmployeeID' => $EmployeeID
        ]); // 執行 SQL

        header("Location: index.php?Act=350&resultsPerPage=$resultsPerPage"); // 轉址回員工列表
        exit(); // 結束程式
    } catch (Exception $e) { // 例外錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 資料庫錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }
} else {
    $EmployeeID = $_GET['id']; // 取得員工 ID
    $resultsPerPage = $_GET['resultsPerPage'] ?? 5; // 預設顯示 5 筆

    // 取得員工資料
    $stmt = $pdo->prepare("SELECT * FROM Employee WHERE EmployeeID = :EmployeeID"); // 準備 SQL
    $stmt->execute([':EmployeeID' => $EmployeeID]); // 執行 SQL
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // 取得查詢結果
}
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'> <!-- 卡片 -->
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;">編輯員工</h3> <!-- 標題 -->
    <hr> <!-- 分隔線 -->
    <form method="POST"> <!-- 表單 -->
        <input type="hidden" name="id" value="<?php echo $row['EmployeeID']; ?>"> <!-- 保留員工 ID -->
        <input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>"> <!-- 保留每頁顯示筆數 -->
        <div class="form-group"> <!-- 表單群組 -->
            <label>員工ID</label> <!-- 標籤 -->
            <input type="text" name="EmployeeID" class="form-control" value="<?php echo $row['EmployeeID']; ?>" readonly> <!-- 員工 ID -->
        </div>
        <div class="form-group"> <!-- 表單群組 -->
            <label>員工姓名</label> <!-- 標籤 -->
            <input type="text" name="EmployeeName" class="form-control" value="<?php echo $row['EmployeeName']; ?>" required> <!-- 員工姓名 -->
        </div> 
        <br>
        <div style="text-align: center;"> <!-- 文字置中 -->
            <a href="index.php?Act=350&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary">返回</a> <!-- 返回 -->
            <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
            <button type="reset" class="btn btn-warning text-white">清除</button> <!-- 清除 -->
            <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
            <button type="submit" class="btn btn-success">修改</button> <!-- 修改 -->
        </div>
    </form>
</div>
