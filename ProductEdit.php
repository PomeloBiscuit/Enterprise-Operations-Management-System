<?php // ProductEdit.php
require_once("config.inc.php"); // 引入資料庫設定檔

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 如果是 POST 請求
    try {
        // 取得表單欄位
        $ProductID = $_POST['id']; // 編輯貨物編號
        $ProductName = $_POST['ProductName']; // 編輯貨物名稱
        $ProductCategory = $_POST['ProductCategory']; // 編輯貨物類別
        $UnitPrice = $_POST['UnitPrice']; // 編輯單價
        $resultsPerPage = $_POST['resultsPerPage']; // 新增此行

        // 執行資料庫 UPDATE
        $stmt = $pdo->prepare("
            UPDATE Product
            SET ProductName = :ProductName, ProductCategory = :ProductCategory, UnitPrice = :UnitPrice
            WHERE ProductID = :ProductID
        "); // 更新貨物名稱、貨物類別、單價
        $stmt->execute([ // 執行 SQL
            ':ProductName' => $ProductName, // 貨物名稱
            ':ProductCategory' => $ProductCategory, // 貨物類別
            ':UnitPrice' => $UnitPrice, // 單價
            ':ProductID' => $ProductID // 貨物編號
        ]); // 執行 SQL
        
        header("Location: index.php?Act=390&resultsPerPage=$resultsPerPage"); // 維持顯示筆數
        exit(); // 結束程式
    } catch (Exception $e) { // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } catch (PDOException $e) { // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    } 
} else { // 如果是 GET 請求
    $ProductID = $_GET['id']; // 取得貨物編號
    $resultsPerPage = $_GET['resultsPerPage'] ?? 5; // 新增此行

    // 取得貨物資料
    $stmt = $pdo->prepare("SELECT * FROM Product WHERE ProductID = :ProductID"); // 查詢貨物資料
    $stmt->execute([':ProductID' => $ProductID]); // 執行 SQL
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // 取得查詢結果
}
?>

<div class="container mt-5"> <!-- 容器 -->
    <div class="card" style="border-radius: 15px;"> <!-- 卡片 -->
        <div class="card-header text-center"> <!-- 卡片標題 -->
            <h3>編輯貨物</h3> <!-- 標題 -->
        </div>
        <div class="card-body"> <!-- 卡片內容 -->
            <form method="POST"> <!-- 表單 -->
                <input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>"> <!-- 新增此行 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>貨物編號</label> <!-- 標籤 -->
                    <input type="text" class="form-control" value="<?php echo $row['ProductID']; ?>" disabled> <!-- 顯示貨物編號但不開放修改 -->
                    <input type="hidden" name="id" value="<?php echo $row['ProductID']; ?>"> <!-- 隱藏欄位 -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>貨物名稱</label> <!-- 標籤 -->
                    <input type="text" name="ProductName" class="form-control" value="<?php echo $row['ProductName']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>貨物類別</label> <!-- 標籤 -->
                    <input type="text" name="ProductCategory" class="form-control" value="<?php echo $row['ProductCategory']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label>單價</label> <!-- 標籤 -->
                    <input type="number" step="0.01" name="UnitPrice" class="form-control" value="<?php echo $row['UnitPrice']; ?>" required> <!-- 輸入框 -->
                </div> <!-- 結束表單群組 -->
                <br> <!-- 斷行 -->
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=390&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary">返回</a> <!-- 返回按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning">清除</button> <!-- 清除按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary">更新</button> <!-- 更新按鈕 -->
                </div> <!-- 結束文字置中 -->
            </form> <!-- 結束表單 -->
        </div> <!-- 結束卡片內容 -->
    </div> <!-- 結束卡片 -->
</div> <!-- 結束容器 -->
