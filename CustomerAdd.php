<?php
require_once("config.inc.php"); // 引入資料庫設定

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 若是 POST 表單送出
    try {
        // 取得表單欄位
        $CustomerName = $_POST['CustomerName']; // 取得 POST 表單欄位
        $CustomerPhoneNumber = $_POST['CustomerPhoneNumber']; // 取得 POST 表單欄位
        $CustomerAddress = $_POST['CustomerAddress']; // 取得 POST 表單欄位
        $resultsPerPage = $_POST['resultsPerPage']; // 取得 POST 表單欄位
        // 執行資料庫 INSERT
        $stmt = $pdo->prepare(" 
            INSERT INTO Customer (CustomerName, CustomerPhoneNumber, CustomerAddress)
            VALUES (:CustomerName, :CustomerPhoneNumber, :CustomerAddress)
        "); // SQL 語法結束
        $stmt->execute([ // 執行 SQL 語法
            ':CustomerName' => $CustomerName, // 指定欄位值
            ':CustomerPhoneNumber' => $CustomerPhoneNumber, // 指定欄位值
            ':CustomerAddress' => $CustomerAddress // 指定欄位值
        ]);
        // 取得新增的 CustomerID
        $customerID = $pdo->lastInsertId(); // 取得最後一次插入的資料表中的 ID
        header("Location: index.php?Act=300&resultsPerPage=$resultsPerPage"); // 導向顧客列表
        exit(); // 結束程式
    } catch (Exception $e) { // 若有錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 若有錯誤
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }
}
?> 

<div class="container mt-5"> <!-- 容器 -->
    <div class="card" style="border-radius: 15px;"> <!-- 卡片 -->
        <div class="card-header text-center"> <!-- 卡片標題 -->
            <h3>新增顧客</h3> <!-- 標題 -->
        </div> <!-- 卡片標題結束 -->
        <div class="card-body"> <!-- 卡片內容 -->
            <form method="POST"> <!-- 表單 -->
                <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 隱藏欄位 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客編號</label> <!-- 標籤 -->
                    <?php // 取得下一個顧客編號
                    try { // 嘗試執行
                        $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'Customer'"); // SQL 語法
                        $row = $stmt->fetch(); // 取得第一筆資料
                        $nextCustomerID = $row['AUTO_INCREMENT']; // 取得 AUTO_INCREMENT 欄位值
                        echo "<input type='text' class='form-control' value='$nextCustomerID' disabled>"; // 顯示顧客編號
                    } catch (PDOException $e) { // 捕捉錯誤
                        echo "<p>無法取得顧客編號：" . htmlspecialchars($e->getMessage()) . "</p>"; // 顯示錯誤訊息
                    }   // 結束執行
                    ?> <!-- 結束 PHP 區塊 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客姓名</label> <!-- 標籤 -->
                    <input type="text" name="CustomerName" class="form-control" placeholder="請輸入顧客姓名" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客電話</label> <!-- 標籤 -->
                    <input type="text" name="CustomerPhoneNumber" class="form-control" placeholder="請輸入顧客電話" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>顧客地址</label> <!-- 標籤 -->
                    <input type="text" name="CustomerAddress" class="form-control" placeholder="請輸入顧客地址" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <br> <!-- 斷行 -->
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=300&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a> <!-- 返回按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning">清除</button> <!-- 清除按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary">送出</button> <!-- 送出按鈕 -->
                </div> <!-- 文字置中結束 -->
            </form> <!-- 表單結束 -->
        </div> <!-- 卡片內容結束 -->
    </div> <!-- 卡片結束 -->
</div> <!-- 容器結束 -->
