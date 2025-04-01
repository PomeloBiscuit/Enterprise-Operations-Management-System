<?php // OrderAdd.php
require_once("config.inc.php"); // 引入資料庫設定檔

function generateTrackingNumber() { // 產生追蹤號碼
    return 'TN' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 產生 6 位數的追蹤號碼
}

$orderID = null; // 訂單編號

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 如果是 POST 請求
    try {
        // 檢查顧客ID、產品ID和員工ID是否存在
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Customer WHERE CustomerID = :CustomerID");  // 查詢顧客ID是否存在
        $stmt->execute([':CustomerID' => $_POST['CustomerID']]);    // 執行 SQL
        if ($stmt->fetchColumn() == 0) {    // 如果查無資料
            throw new Exception("Invalid Customer ID"); // 顧客ID無效
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE ProductID = :ProductID"); // 查詢產品ID是否存在
        $stmt->execute([':ProductID' => $_POST['ProductID']]);      // 執行 SQL
        if ($stmt->fetchColumn() == 0) {    // 如果查無資料
            throw new Exception("Invalid Product ID");  // 產品ID無效
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Employee WHERE EmployeeID = :EmployeeID");  // 查詢員工ID是否存在
        $stmt->execute([':EmployeeID' => $_POST['EmployeeID']]);    // 執行 SQL
        if ($stmt->fetchColumn() == 0) {    // 如果查無資料
            throw new Exception("Invalid Employee ID"); // 員工ID無效
        }

        // 取得表單欄位
        $CustomerID = $_POST['CustomerID']; // 顧客ID
        $ProductID = $_POST['ProductID'];   // 產品ID
        $EmployeeID = $_POST['EmployeeID']; // 員工ID
        $OrderTime = $_POST['OrderTime'];   // 訂單時間
        $ShipDate = $_POST['ShipDate'];    // 出貨日期
        $TrackingNumber = $_POST['TrackingNumber']; // 追蹤號碼
        $ShipMethod = $_POST['ShipMethod']; // 出貨方式
        $resultsPerPage = $_POST['resultsPerPage']; // 新增此行

        // 執行資料庫 INSERT
        $stmt = $pdo->prepare("
            INSERT INTO Orders (CustomerID, ProductID, EmployeeID, OrderTime, ShipDate, TrackingNumber, ShipMethod)
            VALUES (:CustomerID, :ProductID, :EmployeeID, :OrderTime, :ShipDate, :TrackingNumber, :ShipMethod)
        ");
        $stmt->execute([    // 執行 SQL
            ':CustomerID' => $CustomerID,   // 顧客ID
            ':ProductID' => $ProductID,    // 產品ID
            ':EmployeeID' => $EmployeeID,   // 員工ID
            ':OrderTime' => $OrderTime,   // 訂單時間
            ':ShipDate' => $ShipDate,   // 出貨日期
            ':TrackingNumber' => $TrackingNumber,  // 追蹤號碼
            ':ShipMethod' => $ShipMethod    // 出貨方式
        ]); // 執行 SQL

        // 取得新增的 OrderID
        $orderID = $pdo->lastInsertId();    // 取得最後新增的訂單編號
        header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage");   // 重新導向到訂單列表
        exit(); // 結束程式
    } catch (Exception $e) {    // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>";  // 顯示錯誤訊息
    } catch (PDOException $e) { // 例外處理
        echo "<p>Error: " . $e->getMessage() . "</p>";  // 顯示錯誤訊息
    }       
} else {    // 如果是 GET 請求
    try {   // 取得下一個訂單編號
        $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Fiance2024' AND TABLE_NAME = 'Orders'");    // 查詢下一個訂單編號
        $row = $stmt->fetch();  // 取得查詢結果
        $nextOrderID = $row['AUTO_INCREMENT'];  // 取得下一個訂單編號
    } catch (PDOException $e) { // 例外處理
        echo "<p>Unable to retrieve order ID: " . htmlspecialchars($e->getMessage()) . "</p>";  // 顯示錯誤訊息
    }   
}   

// Fetch customers, products, and employees for selection
$customers = $pdo->query("SELECT CustomerID, CustomerName, CustomerPhoneNumber FROM Customer")->fetchAll(PDO::FETCH_ASSOC); // 查詢顧客資料
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);  // 查詢產品資料
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);  // 查詢員工資料
?>  <!-- 結束 PHP 區塊 -->

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>  <!-- 背景顏色、內距、圓角、陰影 -->
    <h3 style='text-align: center; font-family: "Noto Sans TC", "Times New Roman", serif;'>新增訂單</h3><hr>    <!-- 標題 -->
    <form action="OrderAdd.php" method="post">  <!-- 表單 -->
        <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>"> <!-- 新增此行 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Order ID</label> <!-- 標籤 -->
            <input type="text" id="orderID" class="form-control" value="<?php echo isset($nextOrderID) ? $nextOrderID : ''; ?>" disabled> <!-- 顯示訂單編號但不開放修改 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label for="EmployeeID">Employee ID</label> <!-- 標籤 -->
            <input type="text" id="employeeSearch" class="form-control" placeholder="輸入Employee ID or Name">  <!-- 輸入框 -->
            <select name="EmployeeID" id="EmployeeID" class="form-control mt-2">    <!-- 下拉選單 -->
                <option value="">選擇Employee</option>  <!-- 選項 -->
                <?php foreach ($employees as $employee): ?>   <!-- 迴圈 -->
                    <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>  <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label for="CustomerID">Customer ID</label> <!-- 標籤 -->
            <input type="text" id="customerSearch" class="form-control" placeholder="搜尋 Customer ID, Name or Phone">  <!-- 輸入框 -->
            <select name="CustomerID" id="customerID" class="form-control mt-2" required>   <!-- 下拉選單 -->
                <option value="">選擇Customer</option>  <!-- 選項 -->
                <?php foreach ($customers as $customer): ?>  <!-- 迴圈 -->
                    <option value="<?php echo $customer['CustomerID']; ?>"><?php echo $customer['CustomerID'] . ' - ' . $customer['CustomerName']; ?></option>      <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label for="ProductID">Product ID</label>   <!-- 標籤 -->
            <input type="text" id="productSearch" class="form-control" placeholder="輸入ProductID or Name">   <!-- 輸入框 -->
            <select name="ProductID" id="ProductID" class="form-control mt-2">  <!-- 下拉選單 -->
                <option value="">選擇Product</option>   <!-- 選項 -->
                <?php foreach ($products as $product): ?>   <!-- 迴圈 -->
                    <option value="<?php echo $product['ProductID']; ?>">   <!-- 選項 -->
                        <?php echo $product['ProductID'] . ' - ' . $product['ProductName']; ?>      <!-- 顯示產品編號和名稱 -->  
                    </option>   <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Order Time</label>   <!-- 標籤 -->
            <input type="datetime-local" name="OrderTime" class="form-control" required>    <!-- 日期時間選擇器 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Ship Date</label>    <!-- 標籤 -->
            <input type="date" name="ShipDate" class="form-control">    <!-- 日期選擇器 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Tracking Number</label>  <!-- 標籤 -->
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo generateTrackingNumber(); ?>" readonly>   <!-- 顯示追蹤號碼但不開放修改 -->
        </div>      <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Ship Method</label>  <!-- 標籤 -->   
            <select name="ShipMethod" class="form-control">   <!-- 下拉選單 -->
                <option value="Air">Air</option>    <!-- 選項 -->
                <option value="Sea">Sea</option>    <!-- 選項 -->
                <option value="Land">Land</option>  <!-- 選項 -->
            </select>   <!-- 結束下拉選單 -->
        </div>  <!-- 結束表單群組 -->
        <br>    <!-- 斷行 -->
        <div style="text-align: center;">   <!-- 文字置中 -->
            <a href="index.php?Act=430&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a>   <!-- 返回按鈕 -->
            <span style='display: inline-block; width: 20px;'></span>   <!-- 空白 -->
            <button type="reset" class="btn btn-warning text-white">清除</button>   <!-- 清除按鈕 -->
            <span style='display: inline-block; width: 20px;'></span>   <!-- 空白 -->
            <button type="submit" class="btn btn-primary">新增</button>  <!-- 新增按鈕 -->
        </div>
    </form>
</div>

<script src="js/jquery-3.6.0.min.js"></script>  <!-- 引入 jQuery -->
<script>
$(document).ready(function() {  // 等待文件載入完成後執行
    $('#employeeSearch').on('input', function() {   // 當輸入框輸入時
        var searchValue = $(this).val().toLowerCase();  // 取得輸入值
        $('#EmployeeID option').each(function() {   // 遍歷員工下拉選單
            var text = $(this).text().toLowerCase();    // 取得選項文字
            if (text.includes(searchValue)) {   // 如果選項文字包含輸入值
                $(this).prop('selected', true); // 選取該選項
                return false;   // 結束遍歷
            }   
        }); 
    }); 

    $('#productSearch').on('input', function() {    // 當輸入框輸入時
        var searchValue = $(this).val().toLowerCase();   // 取得輸入值
        $('#ProductID option').each(function() {    // 遍歷產品下拉選單
            var text = $(this).text().toLowerCase();    // 取得選項文字
            if (text.includes(searchValue)) {   // 如果選項文字包含輸入值
                $(this).prop('selected', true); // 選取該選項
                return false;   // 結束遍歷
            }
        });
    });

    $('#customerSearch').on('input', function() {   // 當輸入框輸入時
        var searchValue = $(this).val().toLowerCase();  // 取得輸入值
        $('#customerID option').each(function() {   // 遍歷顧客下拉選單
            var text = $(this).text().toLowerCase();    // 取得選項文字
            if (text.includes(searchValue)) {   // 如果選項文字包含輸入值
                $(this).prop('selected', true); // 選取該選項
                return false;       // 結束遍歷
            }
        });
    });

    $('#EmployeeID').on('change', function() {  // 當員工下拉選單選取時
        var selectedText = $('#EmployeeID option:selected').text();  // 取得選取的選項文字
        $('#employeeSearch').val(selectedText.split(' - ')[0]); // 顯示員工ID
    });

    $('#ProductID').on('change', function() {   // 當產品下拉選單選取時
        var selectedText = $('#ProductID option:selected').text();  // 取得選取的選項文字
        $('#productSearch').val(selectedText.split(' - ')[0]);  // 顯示產品ID
    });

    $('#customerID').on('change', function() {  // 當顧客下拉選單選取時
        var selectedText = $('#customerID option:selected').text();  // 取得選取的選項文字
        $('#customerSearch').val(selectedText.split(' - ')[0]); // 顯示顧客ID
    });

    // 自動隱藏 ShipDate 的日期選擇器
    const shipDateInput = document.querySelector('input[name="ShipDate"]'); // 取得出貨日期輸入框
    shipDateInput.addEventListener('change', function() {   // 當日期選擇器改變時
        shipDateInput.blur(); // 失去焦點，隱藏日曆
    });

    // 自動隱藏 Order Time 的日期時間選擇器
    const orderTimeInput = document.querySelector('input[name="OrderTime"]');   // 取得訂單時間輸入框
    orderTimeInput.addEventListener('change', function() {  // 當日期時間選擇器改變時
        orderTimeInput.blur(); // 失去焦點，隱藏日期時間選擇器
    });
});

document.getElementById('customerSearch').addEventListener('input', function() {    // 當輸入框輸入時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($customers); ?>; // 取得顧客資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.CustomerName.toLowerCase().includes(searchValue) ||  // 顧客名稱包含輸入值
        option.CustomerPhoneNumber.toLowerCase().includes(searchValue) ||   // 顧客電話包含輸入值
        option.CustomerID.toString().includes(searchValue)  // 顧客ID包含輸入值
    );
    const customerSelect = document.getElementById('customerID');   // 取得顧客下拉選單
    customerSelect.innerHTML = '<option value="">選擇顧客</option>';    // 清空選項
    filteredOptions.forEach(option => { // 遍歷過濾後的選項
        const opt = document.createElement('option');   // 創建選項
        opt.value = option.CustomerID;  // 設定選項值
        opt.textContent = `${option.CustomerID} - ${option.CustomerName}`;  // 設定選項文字
        customerSelect.appendChild(opt);    // 加入選項
    });
    if (filteredOptions.length === 1) {   // 如果只有一個選項
        customerSelect.value = filteredOptions[0].CustomerID;   // 選取該選項
    }
});

document.getElementById('productSearch').addEventListener('input', function() {   // 當輸入框輸入時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($products); ?>;  // 取得產品資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.ProductName.toLowerCase().includes(searchValue) ||   // 產品名稱包含輸入值
        option.ProductID.toString().includes(searchValue)   // 產品ID包含輸入值
    );
    const productSelect = document.getElementById('ProductID');  // 取得產品下拉選單
    productSelect.innerHTML = '<option value="">選擇產品</option>'; // 清空選項
    filteredOptions.forEach(option => {  // 遍歷過濾後的選項
        const opt = document.createElement('option');   // 創建選項
        opt.value = option.ProductID;   // 設定選項值
        opt.textContent = `${option.ProductID} - ${option.ProductName}`;    // 設定選項文字
        productSelect.appendChild(opt); // 加入選項
    });
    if (filteredOptions.length === 1) {  // 如果只有一個選項
        productSelect.value = filteredOptions[0].ProductID; // 選取該選項
    }
});

document.getElementById('employeeSearch').addEventListener('input', function() {    // 當輸入框輸入時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($employees); ?>; // 取得員工資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.EmployeeName.toLowerCase().includes(searchValue) ||  // 員工名稱包含輸入值
        option.EmployeeID.toString().includes(searchValue)  // 員工ID包含輸入值
    );
    const employeeSelect = document.getElementById('EmployeeID');   // 取得員工下拉選單
    employeeSelect.innerHTML = '<option value="">選擇員工</option>';    // 清空選項
    filteredOptions.forEach(option => { // 遍歷過濾後的選項
        const opt = document.createElement('option');   // 創建選項
        opt.value = option.EmployeeID;  // 設定選項值
        opt.textContent = `${option.EmployeeID} - ${option.EmployeeName}`;  // 設定選項文字
        employeeSelect.appendChild(opt);    // 加入選項
    });
    if (filteredOptions.length === 1) {  // 如果只有一個選項
        employeeSelect.value = filteredOptions[0].EmployeeID;   // 選取該選項
    }
});

document.getElementById('customerSearch').addEventListener('blur', function() {   // 當輸入框失去焦點時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($customers); ?>; // 取得顧客資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.CustomerName.toLowerCase().includes(searchValue) ||  // 顧客名稱包含輸入值
        option.CustomerPhoneNumber.toLowerCase().includes(searchValue) ||   // 顧客電話包含輸入值
        option.CustomerID.toString().includes(searchValue)  // 顧客ID包含輸入值
    );
    if (filteredOptions.length === 1) {  // 如果只有一個選項
        document.getElementById('customerID').value = filteredOptions[0].CustomerID;    // 選取該選項
    } else {    // 否則
        const exactMatches = options.filter(option => option.CustomerID.toString() === searchValue || option.CustomerName.toLowerCase() === searchValue);   // 精確匹配
        if (exactMatches.length === 1) {    // 如果只有一個精確匹配
            document.getElementById('customerID').value = exactMatches[0].CustomerID;   // 選取該選項
        } else {    // 否則
            document.getElementById('customerID').value = '';   // 清空顧客ID
        }
    }
});

document.getElementById('productSearch').addEventListener('blur', function() {  // 當輸入框失去焦點時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($products); ?>;  // 取得產品資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.ProductName.toLowerCase().includes(searchValue) ||   // 產品名稱包含輸入值
        option.ProductID.toString().includes(searchValue)   // 產品ID包含輸入值
    );
    if (filteredOptions.length === 1) { // 如果只有一個選項
        document.getElementById('ProductID').value = filteredOptions[0].ProductID;  // 選取該選項
    } else {    // 否則
        const exactMatches = options.filter(option => option.ProductID.toString() === searchValue || option.ProductName.toLowerCase() === searchValue); // 精確匹配
        if (exactMatches.length === 1) {    // 如果只有一個精確匹配
            document.getElementById('ProductID').value = exactMatches[0].ProductID;  // 選取該選項
        } else {    // 否則
            document.getElementById('ProductID').value = '';    // 清空產品ID
        }
    }
});

document.getElementById('employeeSearch').addEventListener('blur', function() {   // 當輸入框失去焦點時
    const searchValue = this.value.toLowerCase();   // 取得輸入值
    const options = <?php echo json_encode($employees); ?>; // 取得員工資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.EmployeeName.toLowerCase().includes(searchValue) ||  // 員工名稱包含輸入值
        option.EmployeeID.toString().includes(searchValue)  // 員工ID包含輸入值
    );
    if (filteredOptions.length === 1) { // 如果只有一個選項
        document.getElementById('EmployeeID').value = filteredOptions[0].EmployeeID;    // 選取該選項
    } else {    // 否則
        const exactMatches = options.filter(option => option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue);   // 精確匹配
        if (exactMatches.length === 1) {    // 如果只有一個精確匹配
            document.getElementById('EmployeeID').value = exactMatches[0].EmployeeID;   // 選取該選項
        } else {    // 否則
            document.getElementById('EmployeeID').value = '';   // 清空員工ID
        }
    }
});

function validateForm() {   // 驗證表單
    const customerID = document.getElementById('customerID').value; // 取得顧客ID
    const productID = document.getElementById('ProductID').value;   // 取得產品ID
    const employeeID = document.getElementById('EmployeeID').value; // 取得員工ID
    if (!customerID || !productID || !employeeID) {   // 如果顧客ID、產品ID或員工ID為空
        alert('Please select valid Customer ID, Product ID, and Employee ID');  // 提示選擇有效的顧客ID、產品ID和員工ID
        return false;   // 回傳 false
    }
    return true;    // 回傳 true
}
</script>   <!-- 結束 JavaScript 區塊 -->
</body> <!-- 結束 body 標籤 -->
</html> <!-- 結束 html 標籤 -->
