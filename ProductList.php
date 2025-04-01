<?php // ProductList.php 
if ($_SESSION["admlimit"] > 0) { // 確認是否有權限
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; // 預設排序為 ASC
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc'; // 下一次排序的順序
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'ProductID'; // 預設排序欄位為 ProductID
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : ''); // 預設搜尋欄位為空
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : ''); // 預設搜尋值為空
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 5); // 預設顯示 5 筆資料
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1; // 預設顯示第 1 頁
    $offset = ($page - 1) * $resultsPerPage; // 計算偏移量

    echo " 
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'> <!-- 容器 -->
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>貨物列表</h3><hr> <!-- 標題 -->

        <!-- 搜尋框和新增按鈕 --> 
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'> <!-- 搜尋表單 -->
            <div style='display: flex; align-items: center; gap: 10px;'> <!-- 顯示筆數和搜尋欄位 -->
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label> <!-- 顯示筆數標籤 -->
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;' onchange='this.form.submit()'> <!-- 顯示筆數下拉式選單 -->
                    <option value='5' " . ($resultsPerPage === 5 ? 'selected' : '') . ">5</option> <!-- 預設顯示 5 筆資料 -->
                    <option value='10' " . ($resultsPerPage === 10 ? 'selected' : '') . ">10</option> <!-- 選項 -->
                    <option value='15' " . ($resultsPerPage === 15 ? 'selected' : '') . ">15</option> <!-- 選項 -->
                    <option value='20' " . ($resultsPerPage === 20 ? 'selected' : '') . ">20</option> <!-- 選項 -->
                    <option value='25' " . ($resultsPerPage === 25 ? 'selected' : '') . ">25</option> <!-- 選項 -->
                    <option value='50' " . ($resultsPerPage === 50 ? 'selected' : '') . ">50</option> <!-- 選項 -->
                </select> <!-- 顯示筆數下拉式選單結束 --> 
            </div> <!-- 顯示筆數和搜尋欄位結束 -->
            &nbsp;&nbsp;&nbsp;&nbsp; <!-- 空白 -->
            <select name='searchColumn' class='form-select' style='max-width: 200px;'> <!-- 搜尋欄位下拉式選單 -->
                <option value=''>選擇搜尋條件</option> <!-- 預設選項 -->
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">全部</option>   <!-- 選項 -->
                <option value='ProductID' " . ($searchColumn === 'ProductID' ? 'selected' : '') . ">Product ID</option> <!-- 選項 -->
                <option value='ProductName' " . ($searchColumn === 'ProductName' ? 'selected' : '') . ">Product Name</option> <!-- 選項 -->
                <option value='ProductCategory' " . ($searchColumn === 'ProductCategory' ? 'selected' : '') . ">Product Category</option> <!-- 選項 -->
                <option value='UnitPrice' " . ($searchColumn === 'UnitPrice' ? 'selected' : '') . ">Unit Price</option> <!-- 選項 -->
            </select> <!-- 搜尋欄位下拉式選單結束 -->
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>   <!-- 搜尋輸入框 -->

            <button type='submit' class='btn btn-primary'>搜尋</button> <!-- 搜尋按鈕 -->
            <a href='index.php?Act=390&resultsPerPage=$resultsPerPage' class='btn btn-secondary'>顯示所有資料</a> <!-- 顯示所有資料按鈕 -->
            <a href='index.php?Act=400&resultsPerPage=$resultsPerPage' class='btn btn-success'>新增貨物</a> <!-- 新增貨物按鈕 -->
        </form> <!-- 搜尋表單結束 -->

        <form id='deleteForm' method='post' action='ProductDelBatch.php' onsubmit='return confirmDelete();'> <!-- 刪除表單 -->
            <input type='hidden' name='resultsPerPage' value='$resultsPerPage'> <!-- 隱藏欄位，傳遞顯示筆數 -->
            <table class=\"table table-bordered table-hover\" style='width: 100%;'> <!-- 資料表格 -->
            <thead> <!-- 表頭 -->
                <tr> <!-- 表頭列 --> 
                    <th style='text-align: center; vertical-align: middle; width: 60px;'>全選<br><input type='checkbox' id='selectAll'></th> <!-- 全選欄位 -->
                    <th style='text-align: center; vertical-align: middle;'><a href='?Act=390&sort=$nextSortOrder&sortColumn=ProductID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>ProductID</a></th> <!-- 貨物編號 -->
                    <th style='text-align: center; vertical-align: middle;'><a href='?Act=390&sort=" . ($sortColumn === 'ProductName' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=ProductName&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>ProductName</a></th> <!-- 貨物名稱 -->
                    <th style='text-align: center; vertical-align: middle;'>Product<br>Category</th> <!-- 貨物類別 -->
                    <th style='text-align: center; vertical-align: middle;'>UnitPrice</th> <!-- 單價 -->
                    <th style='text-align: center; vertical-align: middle; width: 75px;'>功能</th> <!-- 操作 -->
                </tr> <!-- 表頭列結束 -->
            </thead> <!-- 表頭結束 -->
            <tbody> <!-- 表身 -->
    ";

    try {
        // 設定查詢條件
        $query = "SELECT * FROM Product"; // 查詢所有欄位
        if ($searchColumn && $searchValue) { // 有選擇搜尋條件且有輸入搜尋內容
            if ($searchColumn === 'all') { // 搜尋全部欄位
                $query .= " WHERE (ProductID LIKE :searchValue OR ProductName LIKE :searchValue OR ProductCategory LIKE 
                :searchValue OR UnitPrice LIKE :searchValue)"; // 使用 OR 運算子進行模糊搜尋
            } else { // 搜尋指定欄位
                $query .= " WHERE $searchColumn LIKE :searchValue"; // 使用 LIKE 運算子進行模糊搜尋
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') { // 有選擇搜尋條件但未輸入搜尋內容
            $query .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        } // 設定查詢條件結束
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset"; // 排序和分頁

        $stmt = $pdo->prepare($query); // 準備查詢
        if ($searchColumn && $searchValue) { // 綁定搜尋條件
            $stmt->bindValue(':searchValue', "%$searchValue%"); // 使用 LIKE 運算子進行模糊搜尋
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT); // 綁定顯示筆數
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // 綁定偏移量
        $stmt->execute(); // 執行查詢

        $results = $stmt->fetchAll();   // 取得查詢結果
        if (count($results) > 0) {  // 顯示查詢結果
            foreach ($results as $row) {    // 逐筆顯示
                echo " <!-- 顯示資料列 -->
                <tr> <!-- 資料列 -->
                    <td style='text-align: center;'><input type='checkbox' name='selectedProducts[]' value='{$row['ProductID']}'></td> <!-- 勾選欄位 -->
                    <td style='text-align: center;'>{$row['ProductID']}</td> <!-- 貨物編號 -->
                    <td style='text-align: center;'>{$row['ProductName']}</td> <!-- 貨物名稱 -->
                    <td style='text-align: center;'>{$row['ProductCategory']}</td> <!-- 貨物類別 -->
                    <td style='text-align: center;'>{$row['UnitPrice']}</td> <!-- 單價 -->
                    <td style='text-align: center;'> <!-- 操作 -->
                        <a href='index.php?Act=420&id={$row['ProductID']}&resultsPerPage=$resultsPerPage' class='btn btn-primary btn-sm'>編輯</a> <!-- 編輯按鈕 -->
                    </td> <!-- 操作結束 -->
                </tr> <!-- 資料列結束 -->
                "; // 顯示資料列
            } // 顯示查詢結果結束
        } else {   // 查無資料
            echo "<tr><td colspan='6' style='text-align: center;'>查無資料</td></tr>"; // 顯示查無資料
        } // 查無資料結束

        // 計算總頁數
        $countQuery = "SELECT COUNT(*) FROM Product"; // 計算總筆數
        if ($searchColumn && $searchValue) { // 有選擇搜尋條件且有輸入搜尋內容
            if ($searchColumn === 'all') { // 搜尋全部欄位
                $countQuery .= " WHERE (ProductID LIKE :searchValue OR ProductName LIKE :searchValue OR ProductCategory LIKE :searchValue OR UnitPrice LIKE :searchValue)"; // 使用 OR 運算子進行模糊搜尋
            } else { // 搜尋指定欄位
                $countQuery .= " WHERE $searchColumn LIKE :searchValue"; // 使用 LIKE 運算子進行模糊搜尋
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') { // 有選擇搜尋條件但未輸入搜尋內容
            $countQuery .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }

        $countStmt = $pdo->prepare($countQuery); // 準備計算總筆數
        if ($searchColumn && $searchValue) { // 綁定搜尋條件
            $countStmt->bindValue(':searchValue', "%$searchValue%"); // 使用 LIKE 運算子進行模糊搜尋
        } 
        $countStmt->execute(); // 執行計算總筆數
        $totalResults = $countStmt->fetchColumn(); // 取得總筆數
        $totalPages = $totalResults > 0 ? ceil($totalResults / $resultsPerPage) : 1; // 確保 totalPages 至少為 1

    } catch (PDOException $e) { // 處理 PDO 例外
        echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }

    echo " <!-- 資料表格結束 -->
            </tbody> <!-- 表身結束 -->
            </table> <!-- 資料表格結束 -->
            <div> <!-- 操作按鈕 -->
                <button type='submit' class='btn btn-danger'>刪除勾選的資料</button> <!-- 刪除按鈕 -->
            </div> <!-- 操作按鈕結束 -->
            <div style='margin-top: 15px; background-color: white; text-align: center;'>    
    "; 
 
    // 分頁導航
    if ($totalPages > 1) { // 當總頁數大於 1 時，顯示分頁導航
        echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>"; // 分頁導航
        echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>"; // 分頁樣式
        if ($page > 1) { // 當前頁數大於 1 時，顯示上一頁按鈕
            echo "<li class='page-item'><a class='page-link' href='?Act=390&page=" . ($page - 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>"; // 上一頁按鈕
        } 
        for ($i = 1; $i <= $totalPages; $i++) { // 顯示分頁按鈕
            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=390&page=$i&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>"; // 顯示分頁按鈕
        } 
        if ($page < $totalPages) { // 當前頁數小於總頁數時，顯示下一頁按鈕
            echo "<li class='page-item'><a class='page-link' href='?Act=390&page=" . ($page + 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>"; // 下一頁按鈕
        } 
        echo "</ul></nav>"; // 分頁導航結束
    }

    echo "
            </div> <!-- 分頁導航結束 -->
        </form> <!-- 刪除表單結束 -->
    </div> <!-- 容器結束 -->
    ";
} else { // 權限不足
    echo "<p style='text-align:center; color:red;'>權限不足!</p>"; // 顯示權限不足
} // 權限不足結束
?> 

<script> // JavaScript
document.getElementById('selectAll').addEventListener('click', function(event) { //-- 全選功能
    const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]'); // 取得所有勾選框
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked); // 勾選框狀態與全選框狀態一致
}); // 全選功能結束

function confirmDelete() { // 確認刪除
    const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]:checked'); // 取得所有勾選框
    if (checkboxes.length === 0) { // 當未勾選任何勾選框時
        alert('未選擇任何貨物！'); // 顯示警告訊息
        return false; // 禁止提交表單
    } // 當未勾選任何勾選框時結束
    return confirm('確定要刪除選中的貨物嗎？'); // 確認是否刪除
} 
</script> <!-- JavaScript 結束 -->
