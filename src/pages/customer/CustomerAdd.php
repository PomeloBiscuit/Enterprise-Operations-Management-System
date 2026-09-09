<?php
require_once __DIR__ . '/../../config.inc.php'; // 引入資料庫設定
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

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
            <h3><?php echo t('customer.add.title'); ?></h3> <!-- 標題 -->
        </div> <!-- 卡片標題結束 -->
        <div class="card-body"> <!-- 卡片內容 -->
            <form method="POST"> <!-- 表單 -->
                <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 隱藏欄位 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label><?php echo t('customer.field.id'); ?></label> <!-- 標籤 -->
                    <?php // 取得下一個顧客編號
                    try { // 嘗試執行
                        $stmt = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'Customer'), 0) + 1 AS AUTO_INCREMENT"); // SQLite：sqlite_sequence 取代 information_schema
                        $row = $stmt->fetch(); // 取得第一筆資料
                        $nextCustomerID = $row['AUTO_INCREMENT']; // 取得 AUTO_INCREMENT 欄位值
                        echo "<input type='text' class='form-control' value='$nextCustomerID' disabled>"; // 顯示顧客編號
                    } catch (PDOException $e) { // 捕捉錯誤
                        echo "<p>" . t('customer.add.id_error_prefix') . htmlspecialchars($e->getMessage()) . "</p>"; // 顯示錯誤訊息
                    }   // 結束執行
                    ?> <!-- 結束 PHP 區塊 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label><?php echo t('customer.field.name'); ?></label> <!-- 標籤 -->
                    <input type="text" name="CustomerName" class="form-control" placeholder="<?php echo htmlspecialchars(t('customer.add.name_ph')); ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label><?php echo t('customer.field.phone'); ?></label> <!-- 標籤 -->
                    <input type="text" name="CustomerPhoneNumber" class="form-control" placeholder="<?php echo htmlspecialchars(t('customer.add.phone_ph')); ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <div class="form-group"> <!-- 表單群組 -->
                    <label><?php echo t('customer.field.address'); ?></label> <!-- 標籤 -->
                    <input type="text" name="CustomerAddress" class="form-control" placeholder="<?php echo htmlspecialchars(t('customer.add.address_ph')); ?>" required> <!-- 輸入框 -->
                </div> <!-- 表單群組結束 -->
                <br> <!-- 斷行 -->
                <div class="text-center"> <!-- 文字置中 -->
                    <a href="index.php?Act=300&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a> <!-- 返回按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="reset" class="btn btn-warning"><?php echo t('common.clear'); ?></button> <!-- 清除按鈕 -->
                    <span style='display: inline-block; width: 20px;'></span> <!-- 空白 -->
                    <button type="submit" class="btn btn-primary"><?php echo t('common.submit'); ?></button> <!-- 送出按鈕 -->
                </div> <!-- 文字置中結束 -->
            </form> <!-- 表單結束 -->
        </div> <!-- 卡片內容結束 -->
    </div> <!-- 卡片結束 -->
</div> <!-- 容器結束 -->
