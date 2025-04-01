<?php // ProductAdd.php
require_once("config.inc.php"); // 引入資料庫設定檔

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 如果是 POST 請求
    try { // 例外處理
        // 取得表單欄位
        $ProductName = $_POST['ProductName']; // 新增貨物名稱
        $ProductCategory = $_POST['ProductCategory']; // 新增貨物類別
        $UnitPrice = $_POST['UnitPrice']; // 新增單價
        $resultsPerPage = $_POST['resultsPerPage']; // 新增此行

        // 執行資料庫 INSERT
        $stmt = $pdo->prepare(" 
            INSERT INTO Product (ProductName, ProductCategory, UnitPrice)
            VALUES (:ProductName, :ProductCategory, :UnitPrice) 
        "); // 新增貨物名稱、貨物類別、單價
        $stmt->execute([ 
            ':ProductName' => $ProductName,
            ':ProductCategory' => $ProductCategory, 
            ':UnitPrice' => $UnitPrice
        ]); // 執行 SQL
        // 取得新增的 ProductID
        $productID = $pdo->lastInsertId(); // 取得最後一次插入的 ID
        header("Location: index.php?Act=390&resultsPerPage=$resultsPerPage"); // 維持顯示筆數
        exit(); // 結束程式
    } catch (Exception $e) { // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }
}
?> 

<div class="container mt-5"> <!-- 容器 -->
    <div class="card" style="border-radius: 15px;"> <!-- 卡片 -->
        <div class="card-header text-center"> <!-- 卡片標題 -->
            <h3>新增貨物</h3> <!-- 標題 -->
        </div> <!-- 結束卡片標題 -->
        <div class="card-body"> <!-- 卡片內容 -->
            <form method="POST"> <!-- 表單 -->
                <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 新增此行 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>Product ID</label> <!-- 標籤 -->
                    <?php // 取得下一個 ProductID
                    try { // 例外處理
                        $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'Product'"); // 查詢下一個 ProductID
                        $row = $stmt->fetch(); // 取得查詢結果
                        $nextProductID = $row['AUTO_INCREMENT']; // 取得下一個 ProductID
                        echo "<input type='text' class='form-control' value='$nextProductID' disabled>"; // 顯示下一個 ProductID
                    } catch (PDOException $e) { // 例外處理
                        echo "<p>無法取得貨物編號：" . htmlspecialchars($e->getMessage()) . "</p>"; // 顯示錯誤訊息
                    } 
                    ?> <!-- 結束取得下一個 ProductID -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>Product Name</label> <!-- 標籤 -->
                    <input type="text" name="ProductName" class="form-control" placeholder="請輸入貨物名稱" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>Product Category</label> <!-- 標籤 -->
                    <input type="text" name="ProductCategory" class="form-control" placeholder="請輸入貨物類別" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>Unit Price</label> <!-- 標籤 -->
                    <input type="number" step="0.01" name="UnitPrice" class="form-control" placeholder="請輸入單價" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <br> <!-- 斷行 -->
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=390&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a> <!-- 返回按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning">清除</button> <!-- 清除按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary">送出</button> <!-- 送出按鈕 -->
                </div> <!-- 結束文字置中 -->
            </form> <!-- 結束表單 -->
        </div> <!-- 結束卡片內容 -->
    </div> <!-- 結束卡片 -->
</div> <!-- 結束容器 -->
</body> <!-- 結束頁面主體 -->
</html> <!-- 結束 HTML 文件 -->
