<?php   // CustomerEdit.php
require_once("config.inc.php"); // 引入資料庫設定

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 若是 POST 表單送出
    try {
        // 取得表單欄位
        $CustomerID = $_POST['id']; // 取得 POST 表單欄位
        $CustomerName = $_POST['CustomerName']; // 取得 POST 表單欄位
        $CustomerPhoneNumber = $_POST['CustomerPhoneNumber']; // 取得 POST 表單欄位
        $CustomerAddress = $_POST['CustomerAddress']; // 取得 POST 表單欄位
        $resultsPerPage = $_POST['resultsPerPage']; // 取得 POST 表單欄位

        // 執行資料庫 UPDATE
        $stmt = $pdo->prepare("
            UPDATE Customer
            SET CustomerName = :CustomerName, CustomerPhoneNumber = :CustomerPhoneNumber, CustomerAddress = :CustomerAddress
            WHERE CustomerID = :CustomerID  
              AND EXISTS ( 
                  SELECT 1
                  FROM Customer
                  WHERE CustomerID = :CustomerID
              )
        "); // SQL 語法結束
        $stmt->execute([ // 執行 SQL 語法
            ':CustomerName' => $CustomerName, // 指定欄位值
            ':CustomerPhoneNumber' => $CustomerPhoneNumber, // 指定欄位值
            ':CustomerAddress' => $CustomerAddress, // 指定欄位值
            ':CustomerID' => $CustomerID // 指定欄位值
        ]); // 結束執行

        header("Location: index.php?Act=300&resultsPerPage=$resultsPerPage"); // 導向顧客列表
        exit(); // 結束程式
    } catch (Exception $e) { // 若有錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 若有錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } // 結束執行
} else { // 若非 POST 表單送出
    $CustomerID = $_GET['id']; // 取得 GET 參數
    $resultsPerPage = $_GET['resultsPerPage'] ?? 5; // 取得 GET 參數

    // 取得顧客資料
    $stmt = $pdo->prepare("SELECT * FROM Customer WHERE CustomerID = :CustomerID"); // SQL 語法
    $stmt->execute([':CustomerID' => $CustomerID]); // 執行 SQL 語法
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // 取得第一筆資料
}
?> <!-- 結束 PHP 區塊 -->

<div class="container mt-5"> <!-- 容器 -->
    <div class="card" style="border-radius: 15px;"> <!-- 卡片 -->
        <div class="card-header text-center"> <!-- 卡片標題 -->
            <h3>編輯顧客</h3> <!-- 標題 -->
        </div> <!-- 卡片標題結束 -->
        <div class="card-body"> <!-- 卡片內容 -->
            <form method="POST"> <!-- 表單 -->
                <input type="hidden" name="id" value="<?php echo $row['CustomerID']; ?>"> <!-- 隱藏欄位 -->
                <input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>"> <!-- 隱藏欄位 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客姓名</label> <!-- 標籤 -->
                    <input type="text" name="CustomerName" class="form-control" value="<?php echo $row['CustomerName']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客電話</label>     <!-- 標籤 -->
                    <input type="text" name="CustomerPhoneNumber" class="form-control" value="<?php echo $row['CustomerPhoneNumber']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客地址</label> <!-- 標籤 -->
                    <input type="text" name="CustomerAddress" class="form-control" value="<?php echo $row['CustomerAddress']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <br> <!-- 斷行 -->
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=300&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary">返回</a> <!-- 返回按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning">清除</button> <!-- 清除按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary">更新</button> <!-- 更新按鈕 -->
                </div> <!-- 文字置中結束 -->
            </form> <!-- 表單結束 -->
        </div> <!-- 卡片內容結束 -->
    </div> <!-- 卡片結束 -->
</div> <!-- 容器結束 -->
