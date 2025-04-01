<?php   // OrderEdit.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {    // 如果是 POST 請求
    try {   // 例外處理 
        $OrderID = $_POST['OrderID'];   // 取得表單欄位
        $CustomerID = $_POST['CustomerID']; // 取得表單欄位
        $ProductID = $_POST['ProductID'];   // 取得表單欄位
        $EmployeeID = $_POST['EmployeeID']; // 取得表單欄位
        $OrderTime = $_POST['OrderTime'];   // 取得表單欄位
        $ShipDate = $_POST['ShipDate']; // 取得表單欄位
        $TrackingNumber = $_POST['TrackingNumber'];  // 取得表單欄位
        $ShipMethod = $_POST['ShipMethod'];  // 取得表單欄位

        $stmt = $pdo->prepare("
            UPDATE Orders
            SET CustomerID = :CustomerID,
                ProductID = :ProductID,
                EmployeeID = :EmployeeID,
                OrderTime = :OrderTime,
                ShipDate = :ShipDate,
                TrackingNumber = :TrackingNumber,
                ShipMethod = :ShipMethod
            WHERE OrderID = :OrderID
        "); // 更新訂單資料
        $stmt->execute([    // 執行 SQL
            ':CustomerID' => $CustomerID,   // 顧客編號
            ':ProductID' => $ProductID, // 產品編號
            ':EmployeeID' => $EmployeeID,   // 員工編號
            ':OrderTime' => $OrderTime, // 訂單時間
            ':ShipDate' => $ShipDate,   // 出貨日期
            ':TrackingNumber' => $TrackingNumber,   // 追蹤編號
            ':ShipMethod' => $ShipMethod,   // 出貨方式
            ':OrderID' => $OrderID  // 訂單編號
        ]);
        $resultsPerPage = $_POST['resultsPerPage'] ?? 5;    // 新增此行
        header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage");   // 顯示訂單列表
        exit(); // 結束程式
    } catch (PDOException $e) { // 例外處理
        echo "<p>錯誤：" . $e->getMessage() . "</p>";   // 顯示錯誤訊息
    }   // 結束例外處理
} else {    // 如果是 GET 請求
    $stmt = $pdo->prepare("SELECT * FROM Orders WHERE OrderID = :OrderID");   // 查詢訂單資料
    $stmt->execute([':OrderID' => $_GET['id']]);    // 執行 SQL
    $row = $stmt->fetch();  // 取得查詢結果
}

// Fetch customers, products, and employees for selection
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer")->fetchAll(PDO::FETCH_ASSOC);  // 查詢顧客資料
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);  // 查詢產品資料
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);  // 查詢員工資料
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>  <!-- 卡片 -->    
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;">編輯訂單</h3>    <!-- 標題 -->
    <hr>    <!-- 分隔線 -->
    <form method="POST">    <!-- 表單 -->
        <input type="hidden" name="OrderID" value="<?php echo $row['OrderID']; ?>">   <!-- 隱藏欄位 -->
        <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>">   <!-- 隱藏欄位 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Order ID</label> <!-- 標籤 -->
            <input type="text" class="form-control" value="<?php echo $row['OrderID']; ?>" disabled>    <!-- 顯示訂單編號但不開放修改 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Employee ID</label>  <!-- 標籤 -->
            <input type="text" id="employeeSearch" class="form-control" placeholder="搜尋 Employee ID or Name">   <!-- 新增搜尋框 -->
            <select name="EmployeeID" id="employeeID" class="form-control" required>    <!-- 下拉式選單 -->
                <option value="">選擇員工</option>  <!-- 選項 -->
                <?php foreach ($employees as $employee): ?>   <!-- 迴圈 -->
                    <option value="<?php echo $employee['EmployeeID']; ?>" <?php echo $employee['EmployeeID'] == $row['EmployeeID'] ? 'selected' : '';
                     ?>><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>   <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉式選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">
            <label>Customer ID</label>
            <!-- 新增搜尋框 -->
            <input type="text" id="customerSearch" class="form-control" placeholder="搜尋 Customer ID or Name"> <!-- 新增搜尋框 -->
            <!-- 新增下拉式選單 -->
            <select name="CustomerID" id="customerID" class="form-control" required>    <!-- 下拉式選單 -->
                <option value="">選擇顧客</option>  <!-- 選項 -->
                <?php foreach ($customers as $customer): ?>  <!-- 迴圈 -->
                    <option value="<?php echo $customer['CustomerID']; ?>" <?php echo $customer['CustomerID'] == $row['CustomerID'] ? 'selected' : '';
                     ?>><?php echo $customer['CustomerID'] . ' - ' . $customer['CustomerName']; ?></option>   <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉式選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Product ID</label>   <!-- 標籤 -->
            <input type="text" id="productSearch" class="form-control" placeholder="搜尋 Product ID or Name">   <!-- 新增搜尋框 -->
            <select name="ProductID" id="productID" class="form-control" required>  <!-- 下拉式選單 -->
                <option value="">選擇產品</option>  <!-- 選項 -->
                <?php foreach ($products as $product): ?>   <!-- 迴圈 -->
                    <option value="<?php echo $product['ProductID']; ?>" <?php echo $product['ProductID'] == $row['ProductID'] ? 'selected' : ''; ?>><?php echo $product['ProductID'] . ' - ' . $product['ProductName']; ?></option>    <!-- 選項 -->
                <?php endforeach; ?>    <!-- 結束迴圈 -->
            </select>   <!-- 結束下拉式選單 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Order Time</label>   <!-- 標籤 -->
            <input type="datetime-local" name="OrderTime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($row['OrderTime'])); ?>" required>    <!-- 輸入框 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Ship Date</label>    <!-- 標籤 -->
            <input type="date" name="ShipDate" class="form-control" value="<?php echo $row['ShipDate']; ?>">    <!-- 輸入框 -->
        </div>      <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Tracking Number</label>  <!-- 標籤 -->
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo $row['TrackingNumber']; ?>" required>   <!-- 輸入框 -->
        </div>  <!-- 結束表單群組 -->
        <div class="form-group">    <!-- 表單群組 -->
            <label>Ship Method</label>  <!-- 標籤 -->
            <!-- 新增搜尋框 -->
            <input type="text" id="shipMethodSearch" class="form-control" placeholder="搜尋 Ship Method (Air, Sea, Land)">  <!-- 新增搜尋框 -->
            <!-- 原本的下拉式選單加上 id -->
            <select name="ShipMethod" id="shipMethod" class="form-control">   <!-- 下拉式選單 -->
                <option value="Air" <?php echo $row['ShipMethod'] == 'Air' ? 'selected' : ''; ?>>Air</option>   <!-- 選項 -->
                <option value="Sea" <?php echo $row['ShipMethod'] == 'Sea' ? 'selected' : ''; ?>>Sea</option>   <!-- 選項 -->
                <option value="Land" <?php echo $row['ShipMethod'] == 'Land' ? 'selected' : ''; ?>>Land</option>    <!-- 選項 -->
            </select>   <!-- 結束下拉式選單 -->
        </div>  <!-- 結束表單群組 -->
        <br>    <!-- 斷行 -->
        <div style="text-align: center;">   <!-- 按鈕置中 -->
            <a href="index.php?Act=430&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a>   <!-- 返回按鈕 -->
            <span style='display: inline-block; width: 20px;'></span>   <!-- 空白 -->
            <button type="reset" class="btn btn-warning text-white">清除</button>   <!-- 清除按鈕 -->
            <span style='display: inline-block; width: 20px;'></span>   <!-- 空白 -->
            <button type="submit" class="btn btn-primary">更新</button>  <!-- 更新按鈕 -->
        </div>  <!-- 結束按鈕置中 -->
    </form> <!-- 結束表單 -->
</div>  <!-- 結束卡片 -->

<script>    // JavaScript
document.getElementById('customerSearch').addEventListener('input', function() {    // 監聽搜尋框輸入事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($customers); ?>;  // 取得顧客資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.CustomerName.toLowerCase().includes(searchValue) ||  // 顧客名稱包含搜尋值
        option.CustomerID.toString().includes(searchValue)  // 顧客編號包含搜尋值
    );  // 結束過濾選項
    const customerSelect = document.getElementById('customerID');   // 取得顧客下拉式選單
    customerSelect.innerHTML = '<option value="">選擇顧客</option>';    // 清空選項
    filteredOptions.forEach(option => { // 迴圈
        const opt = document.createElement('option');   // 建立選項
        opt.value = option.CustomerID;  // 設定值
        opt.textContent = `${option.CustomerID} - ${option.CustomerName}`;  // 設定文字
        customerSelect.appendChild(opt);    // 新增選項
    }); // 結束迴圈
    if (filteredOptions.length === 1) { // 如果只有一個選項
        customerSelect.value = filteredOptions[0].CustomerID;   // 選擇該選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框輸入事件

document.getElementById('productSearch').addEventListener('input', function() {   // 監聽搜尋框輸入事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($products); ?>;  // 取得產品資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.ProductName.toLowerCase().includes(searchValue) ||   // 產品名稱包含搜尋值
        option.ProductID.toString().includes(searchValue)   // 產品編號包含搜尋值
    );  // 結束過濾選項
    const productSelect = document.getElementById('productID');  // 取得產品下拉式選單
    productSelect.innerHTML = '<option value="">選擇產品</option>';   // 清空選項
    filteredOptions.forEach(option => {  // 迴圈
        const opt = document.createElement('option');   // 建立選項
        opt.value = option.ProductID;   // 設定值
        opt.textContent = `${option.ProductID} - ${option.ProductName}`;    // 設定文字
        productSelect.appendChild(opt); // 新增選項
    }); // 結束迴圈
    if (filteredOptions.length === 1) { // 如果只有一個選項
        productSelect.value = filteredOptions[0].ProductID;  // 選擇該選項
    }       // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框輸入事件

document.getElementById('employeeSearch').addEventListener('input', function() {    // 監聽搜尋框輸入事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($employees); ?>; // 取得員工資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.EmployeeName.toLowerCase().includes(searchValue) ||  // 員工名稱包含搜尋值
        option.EmployeeID.toString().includes(searchValue)  // 員工編號包含搜尋值
    );  // 結束過濾選項
    const employeeSelect = document.getElementById('employeeID');   // 取得員工下拉式選單
    employeeSelect.innerHTML = '<option value="">選擇員工</option>';    // 清空選項
    filteredOptions.forEach(option => { // 迴圈
        const opt = document.createElement('option');   // 建立選項
        opt.value = option.EmployeeID;  // 設定值
        opt.textContent = `${option.EmployeeID} - ${option.EmployeeName}`;  // 設定文字
        employeeSelect.appendChild(opt);    // 新增選項
    }); // 結束迴圈
    if (filteredOptions.length === 1) { // 如果只有一個選項
        employeeSelect.value = filteredOptions[0].EmployeeID;   // 選擇該選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框輸入事件

document.getElementById('customerSearch').addEventListener('blur', function() {   // 監聽搜尋框失焦事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($customers); ?>; // 取得顧客資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.CustomerName.toLowerCase().includes(searchValue) ||  // 顧客名稱包含搜尋值
        option.CustomerID.toString().includes(searchValue)  // 顧客編號包含搜尋值
    );  // 結束過濾選項
    if (filteredOptions.length === 1) {  // 如果只有一個選項
        document.getElementById('customerID').value = filteredOptions[0].CustomerID;    // 選擇該選項
    } else {    // 如果不只一個選項
        const exactMatch = options.find(option =>   // 尋找完全符合的選項
            option.CustomerName.toLowerCase() === searchValue ||    // 顧客名稱完全符合
            option.CustomerID.toString() === searchValue    // 顧客編號完全符合
        );  // 結束尋找完全符合的選項
        document.getElementById('customerID').value = exactMatch ? exactMatch.CustomerID : '';  // 選擇完全符合的選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框失焦事件

document.getElementById('productSearch').addEventListener('blur', function() {  // 監聽搜尋框失焦事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($products); ?>;  // 取得產品資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.ProductName.toLowerCase().includes(searchValue) ||   // 產品名稱包含搜尋值
        option.ProductID.toString().includes(searchValue)   // 產品編號包含搜尋值
    );  // 結束過濾選項
    if (filteredOptions.length === 1) { // 如果只有一個選項
        document.getElementById('productID').value = filteredOptions[0].ProductID;  // 選擇該選項
    } else {    // 如果不只一個選項
        const exactMatches = options.filter(option => option.ProductID.toString() === searchValue || option.ProductName.toLowerCase() === searchValue);   // 尋找完全符合的選項
        if (exactMatches.length === 1) {    // 如果只有一個完全符合的選項
            document.getElementById('productID').value = exactMatches[0].ProductID;   // 選擇該選項
        } else {    // 如果不只一個完全符合的選項
            document.getElementById('productID').value = '';    // 清空選擇
        }   // 結束判斷是否只有一個完全符合的選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框失焦事件

document.getElementById('employeeSearch').addEventListener('blur', function() {   // 監聽搜尋框失焦事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const options = <?php echo json_encode($employees); ?>; // 取得員工資料
    const filteredOptions = options.filter(option =>    // 過濾選項
        option.EmployeeName.toLowerCase().includes(searchValue) ||  // 員工名稱包含搜尋值
        option.EmployeeID.toString().includes(searchValue)  // 員工編號包含搜尋值
    );  // 結束過濾選項
    if (filteredOptions.length === 1) { // 如果只有一個選項
        document.getElementById('employeeID').value = filteredOptions[0].EmployeeID;    // 選擇該選項
    } else {    // 如果不只一個選項
        const exactMatches = options.filter(option => option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue);   // 尋找完全符合的選項
        if (exactMatches.length === 1) {    // 如果只有一個完全符合的選項
            document.getElementById('employeeID').value = exactMatches[0].EmployeeID;   // 選擇該選項
        } else {    // 如果不只一個完全符合的選項
            document.getElementById('employeeID').value = '';   // 清空選擇
        }   // 結束判斷是否只有一個完全符合的選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框失焦事件

// Ship Method 搜尋與自動選擇
const shipMethods = ['Air', 'Sea', 'Land'];   // 出貨方式
document.getElementById('shipMethodSearch').addEventListener('input', function() {  // 監聽搜尋框輸入事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const matchingMethods = shipMethods.filter(method => method.toLowerCase().includes(searchValue));   // 過濾選項
    const shipSelect = document.getElementById('shipMethod');   // 取得出貨方式下拉式選單
    shipSelect.innerHTML = '';  // 清空選項
    matchingMethods.forEach(method => { // 迴圈
        const option = document.createElement('option');    // 建立選項
        option.value = method;  // 設定值
        option.textContent = method;    // 設定文字
        shipSelect.appendChild(option); // 新增選項
    }); // 結束迴圈
    if (matchingMethods.length === 1) { // 如果只有一個選項
        shipSelect.value = matchingMethods[0];  // 選擇該選項
    }       // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框輸入事件

document.getElementById('shipMethodSearch').addEventListener('blur', function() {   // 監聽搜尋框失焦事件
    const searchValue = this.value.toLowerCase();   // 取得搜尋值
    const matchingMethods = shipMethods.filter(method => method.toLowerCase().includes(searchValue));   // 過濾選項
    if (matchingMethods.length === 1) { // 如果只有一個選項
        document.getElementById('shipMethod').value = matchingMethods[0];   // 選擇該選項
    } else {    // 如果不只一個選項
        const exactMatch = shipMethods.find(method => method.toLowerCase() === searchValue);    // 尋找完全符合的選項
        document.getElementById('shipMethod').value = exactMatch ? exactMatch : ''; // 選擇完全符合的選項
    }   // 結束判斷是否只有一個選項
}); // 結束監聽搜尋框失焦事件
</script>   <!-- 結束 JavaScript -->
