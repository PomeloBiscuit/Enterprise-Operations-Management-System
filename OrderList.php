<?php // OrderList.php
if ($_SESSION["admlimit"] > 0) { // 判斷是否有登入
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; // 排序方式
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc'; // 下一個排序方式
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'OrderID'; // 排序欄位
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : ''); // 搜尋欄位
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : ''); // 搜尋內容
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 5); // 預設顯示 5 筆資料
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1; // 預設顯示第 1 頁
    $offset = ($page - 1) * $resultsPerPage; // 計算偏移量

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'> <!-- 容器 -->
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>訂單列表</h3><hr> <!-- 標題 -->

        <!-- 搜尋框和新增按鈕 --> 
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'> <!-- 表單 -->
            <div style='display: flex; align-items: center; gap: 10px;'> <!-- 顯示筆數和搜尋欄位 -->
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label> <!-- 顯示筆數標籤 -->
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;' onchange='this.form.submit()'> <!-- 顯示筆數下拉式選單 -->
                    <option value='5' " . ($resultsPerPage === 5 ? 'selected' : '') . ">5</option> <!-- 選項 -->
                    <option value='10' " . ($resultsPerPage === 10 ? 'selected' : '') . ">10</option> <!-- 選項 -->
                    <option value='15' " . ($resultsPerPage === 15 ? 'selected' : '') . ">15</option> <!-- 選項 -->
                    <option value='20' " . ($resultsPerPage === 20 ? 'selected' : '') . ">20</option> <!-- 選項 -->
                    <option value='25' " . ($resultsPerPage === 25 ? 'selected' : '') . ">25</option> <!-- 選項 -->
                    <option value='50' " . ($resultsPerPage === 50 ? 'selected' : '') . ">50</option> <!-- 選項 -->
                </select> <!-- 結束顯示筆數下拉式選單 -->
            </div> <!-- 結束顯示筆數和搜尋欄位 -->
            &nbsp;&nbsp;&nbsp;&nbsp; <!-- 空白 -->
            <select name='searchColumn' class='form-select' style='max-width: 200px;'> <!-- 搜尋欄位下拉式選單 -->
                <option value=''>選擇搜尋條件</option> <!-- 選項 -->
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">All</option> <!-- 選項 -->
                <option value='OrderID' " . ($searchColumn === 'OrderID' ? 'selected' : '') . ">Order ID</option> <!-- 選項 -->
                <option value='CustomerID' " . ($searchColumn === 'CustomerID' ? 'selected' : '') . ">Customer ID</option>  <!-- 選項 -->
                <option value='EmployeeID' " . ($searchColumn === 'EmployeeID' ? 'selected' : '') . ">Employee ID</option>  <!-- 選項 -->
                <option value='ShipDate' " . ($searchColumn === 'ShipDate' ? 'selected' : '') . ">Ship Date</option>    <!-- 選項 -->
                <option value='TrackingNumber' " . ($searchColumn === 'TrackingNumber' ? 'selected' : '') . ">Tracking Number</option>  <!-- 選項 -->
                <option value='ShipMethod' " . ($searchColumn === 'ShipMethod' ? 'selected' : '') . ">Ship Method</option>  <!-- 選項 -->
            </select>   <!-- 結束搜尋欄位下拉式選單 -->
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>   <!-- 搜尋內容輸入框 -->
            
            <button type='submit' class='btn btn-primary'>搜尋</button> <!-- 搜尋按鈕 -->
            <a href='index.php?Act=430&resultsPerPage=$resultsPerPage' class='btn btn-secondary'>顯示所有資料</a>   <!-- 顯示所有資料按鈕 -->
            <a href='index.php?Act=440&resultsPerPage=$resultsPerPage' class='btn btn-success'>新增訂單</a> <!-- 新增訂單按鈕 -->
        </form> <!-- 結束表單 -->

        <form id='deleteForm' method='post' action='OrderDelBatch.php' onsubmit='return confirmDelete();'>  <!-- 刪除表單 -->
            <input type='hidden' name='resultsPerPage' value='$resultsPerPage'> <!-- 隱藏欄位 -->
            <table class=\"table table-bordered table-hover\" style='width: 100%;'> <!-- 表格 -->
            <thead> <!-- 表頭 -->
                <tr>    <!-- 表頭 -->
                    <th style='text-align: center; vertical-align: middle; width: 60px;'>全選<br><input type='checkbox' id='selectAll'></th>    <!-- 全選 -->
                    <th style='text-align: center; vertical-align: middle;'><a href='?Act=430&sort=$nextSortOrder&sortColumn=OrderID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>OrderID</a></th>   <!-- 訂單編號 -->
                    <th style='text-align: center; vertical-align: middle;'><a href='?Act=430&sort=" . ($sortColumn === 'EmployeeID' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=EmployeeID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>EmployeeID</a></th>    <!-- 員工編號 -->
                    <th style='text-align: center; vertical-align: middle;'><a href='?Act=430&sort=" . ($sortColumn === 'CustomerID' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=CustomerID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>CustomerID</a></th>    <!-- 顧客編號 -->
                    <th style='text-align: center; vertical-align: middle;'>OrderTime</th>  <!-- 訂單日期 -->
                    <th style='text-align: center; vertical-align: middle;'>ShipDate</th>   <!-- 出貨日期 -->
                    <th style='text-align: center; vertical-align: middle;'>TrackingNumber</th> <!-- 追蹤號碼 -->
                    <th style='text-align: center; vertical-align: middle;'>ShipMethod</th> <!-- 運送方式 -->
                    <th style='text-align: center; vertical-align: middle; width: 75px;'>功能</th>  <!-- 功能 -->
                </tr>
            </thead>
            <tbody>
    ";

    try {   // 連接資料庫
        // 設定查詢條件
        $query = "
            SELECT Orders.*, Customer.CustomerName, Product.ProductName, Employee.EmployeeName
            FROM Orders
            LEFT JOIN Customer ON Orders.CustomerID = Customer.CustomerID
            LEFT JOIN Product ON Orders.ProductID = Product.ProductID
            LEFT JOIN Employee ON Orders.EmployeeID = Employee.EmployeeID
        ";
        if ($searchValue) { // 如果有輸入搜尋內容
            if (empty($searchColumn) || $searchColumn === 'all') { 
                // 搜尋所有欄位
                $query .= " WHERE (
                    Customer.CustomerName LIKE :searchValue OR 
                    Product.ProductName LIKE :searchValue OR 
                    Orders.ShipDate LIKE :searchValue OR 
                    Orders.TrackingNumber LIKE :searchValue OR 
                    Orders.ShipMethod LIKE :searchValue OR 
                    Orders.CustomerID LIKE :searchValue OR 
                    Orders.OrderID LIKE :searchValue OR 
                    Orders.ProductID LIKE :searchValue OR 
                    Orders.EmployeeID LIKE :searchValue
                )";
            } else { 
                // 搜尋指定欄位
                $query .= " WHERE Orders.$searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') { // 如果有搜尋條件但未輸入搜尋內容
            $query .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";   // 設定排序和分頁

        $stmt = $pdo->prepare($query); // 準備查詢
        if ($searchValue) {
            $stmt->bindValue(':searchValue', "%$searchValue%"); // 綁定搜尋內容
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT); // 綁定顯示筆數
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // 綁定偏移量
        $stmt->execute(); // 執行查詢

        $results = $stmt->fetchAll(); // 取得查詢結果
        if (count($results) > 0) { // 顯示資料
            foreach ($results as $row) { // 顯示每一筆資料
                echo " <!-- 顯示每一筆資料 -->
                <tr>   <!-- 資料列 -->
                    <td style='text-align: center;'><input type='checkbox' name='selectedOrders[]' value='{$row['OrderID']}'></td> <!-- 勾選框 -->
                    <td style='text-align: center;'>{$row['OrderID']}</td> <!-- 訂單編號 -->
                    <td style='text-align: center;'>{$row['EmployeeID']}</td> <!-- 員工編號 -->
                    <td style='text-align: center;'>{$row['CustomerID']}</td> <!-- 顧客編號 -->
                    <td style='text-align: center;'>{$row['OrderTime']}</td> <!-- 訂單日期 -->
                    <td style='text-align: center;'>{$row['ShipDate']}</td> <!-- 出貨日期 -->
                    <td style='text-align: center;'>{$row['TrackingNumber']}</td> <!-- 追蹤號碼 -->
                    <td style='text-align: center;'>{$row['ShipMethod']}</td> <!-- 運送方式 -->
                    <td style='text-align: center;'>
                        <a href='index.php?Act=460&id={$row['OrderID']}&resultsPerPage=$resultsPerPage' class='btn btn-primary btn-sm'>編輯</a> <!-- 編輯按鈕 -->
                    </td> <!-- 結束功能欄 -->
                </tr> <!-- 結束資料列 -->
                "; // 結束顯示每一筆資料
            } // 結束顯示每一筆資料
        } else { // 如果沒有資料
            echo "<tr><td colspan='9' style='text-align: center;'>查無資料</td></tr>"; // 顯示查無資料
        } // 結束顯示資料

        // 計算總頁數
        $countQuery = "
            SELECT COUNT(*) 
            FROM Orders
            LEFT JOIN Customer ON Orders.CustomerID = Customer.CustomerID
            LEFT JOIN Product ON Orders.ProductID = Product.ProductID
            LEFT JOIN Employee ON Orders.EmployeeID = Employee.EmployeeID
        ";
        if ($searchValue) { 
            if (empty($searchColumn) || $searchColumn === 'all') {
                // 搜尋所有欄位
                $countQuery .= " WHERE (
                    Customer.CustomerName LIKE :searchValue OR 
                    Product.ProductName LIKE :searchValue OR 
                    Orders.ShipDate LIKE :searchValue OR 
                    Orders.TrackingNumber LIKE :searchValue OR 
                    Orders.ShipMethod LIKE :searchValue OR 
                    Orders.CustomerID LIKE :searchValue OR 
                    Orders.OrderID LIKE :searchValue OR 
                    Orders.ProductID LIKE :searchValue OR 
                    Orders.EmployeeID LIKE :searchValue
                )";
            } else { 
                // 搜尋指定欄位
                $countQuery .= " WHERE Orders.$searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') { // 如果有搜尋條件但未輸入搜尋內容
            $countQuery .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }

        $countStmt = $pdo->prepare($countQuery); // 準備查詢
        if ($searchValue) {
            $countStmt->bindValue(':searchValue', "%$searchValue%"); // 綁定搜尋內容
        }
        $countStmt->execute(); // 執行查詢
        $totalResults = $countStmt->fetchColumn(); // 取得查詢結果
        $totalPages = ceil($totalResults / $resultsPerPage); // 計算總頁數

    } catch (PDOException $e) { // 例外處理
        echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }

    echo " <!-- 分頁導航 -->
            </tbody>
            </table>
            <div> <!-- 功能按鈕 -->
                <button type='submit' class='btn btn-danger'>刪除勾選的資料</button> <!-- 刪除按鈕 -->
            </div> <!-- 結束功能按鈕 -->
            <div style='margin-top: 15px; background-color: white; text-align: center;'> <!-- 分頁導航 -->
    ";
    
    // 分頁導航
    if ($totalPages > 1) { // 如果總頁數大於 1
        echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>"; // 分頁導航
        echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>"; // 分頁樣式
        if ($page > 1) { // 如果當前頁數大於 1
            echo "<li class='page-item'><a class='page-link' href='?Act=430&page=" . ($page - 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>"; // 上一頁按鈕
        } // 上一頁按鈕
        for ($i = 1; $i <= $totalPages; $i++) { // 顯示所有頁數
            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=430&page=$i&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>"; // 顯示頁數
        } // 顯示所有頁數
        if ($page < $totalPages) { // 如果當前頁數小於總頁數
            echo "<li class='page-item'><a class='page-link' href='?Act=430&page=" . ($page + 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>"; // 下一頁按鈕
        } // 下一頁按鈕
        echo "</ul></nav>";  // 結束分頁樣式
    }

    echo "  <!-- 結束分頁導航 -->
            </div>  <!-- 結束分頁導航 -->
        </form> <!-- 結束刪除表單 -->
    </div>  <!-- 結束容器 -->
    ";
} else {    // 如果沒有登入
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";  // 顯示權限不足
}
?>

<script>    // JavaScript
document.getElementById('selectAll').addEventListener('click', function(event) {    // 全選
    const checkboxes = document.querySelectorAll('input[name="selectedOrders[]"]'); // 取得所有勾選框
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);    // 勾選框狀態
}); // 全選

function confirmDelete() {  // 確認刪除
    const checkboxes = document.querySelectorAll('input[name="selectedOrders[]"]:checked'); // 取得所有勾選框
    if (checkboxes.length === 0) {  // 如果沒有勾選框
        alert('未選擇任何訂單！');  // 顯示警告
        return false;   // 結束
    }   // 如果沒有勾選框
    return confirm('確定要刪除選中的訂單嗎？');   // 確認刪除
}   // 確認刪除
</script>   <!-- 結束 JavaScript -->
