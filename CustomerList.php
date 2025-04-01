<?php
if ($_SESSION["admlimit"] > 0) { // 管理員權限
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; // 排序順序
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc'; // 下一次排序順序
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'CustomerID'; // 預設以 CustomerID 欄位排序
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : ''); // 搜尋條件
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : ''); // 搜尋內容
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 5); // 預設顯示 5 筆資料
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1; // 預設顯示第 1 頁
    $offset = ($page - 1) * $resultsPerPage; // 計算偏移量

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'> <!-- 顯示顧客列表 -->
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>顧客列表</h3><hr> <!-- 顯示標題 -->

        <!-- 搜尋框和新增按鈕 --> 
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'> <!-- 搜尋表單 -->
            <div style='display: flex; align-items: center; gap: 10px;'> <!-- 顯示筆數和搜尋條件 -->
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label> <!-- 顯示筆數標籤 -->
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;' onchange='this.form.submit()'> <!-- 每頁顯示筆數下拉選單 -->
                    <option value='5' " . ($resultsPerPage === 5 ? 'selected' : '') . ">5</option> <!-- 每頁顯示筆數 -->
                    <option value='10' " . ($resultsPerPage === 10 ? 'selected' : '') . ">10</option> <!-- 每頁顯示筆數 -->
                    <option value='15' " . ($resultsPerPage === 15 ? 'selected' : '') . ">15</option> <!-- 每頁顯示筆數 -->
                    <option value='20' " . ($resultsPerPage === 20 ? 'selected' : '') . ">20</option> <!-- 每頁顯示筆數 -->
                    <option value='25' " . ($resultsPerPage === 25 ? 'selected' : '') . ">25</option> <!-- 每頁顯示筆數 -->
                    <option value='50' " . ($resultsPerPage === 50 ? 'selected' : '') . ">50</option> <!-- 每頁顯示筆數 -->
                </select>
            </div>
            &nbsp;&nbsp;&nbsp;&nbsp; <!-- 空白 -->
            <select name='searchColumn' class='form-select' style='max-width: 200px;'> <!-- 搜尋條件下拉選單 -->
                <option value=''>選擇搜尋條件</option> <!-- 預設選項 -->
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">全部</option> <!-- 搜尋全部欄位 -->
                <option value='CustomerID' " . ($searchColumn === 'CustomerID' ? 'selected' : '') . ">Customer ID</option> <!-- 搜尋條件 -->
                <option value='CustomerName' " . ($searchColumn === 'CustomerName' ? 'selected' : '') . ">Customer Name</option> <!-- 搜尋條件 -->
                <option value='CustomerPhoneNumber' " . ($searchColumn === 'CustomerPhoneNumber' ? 'selected' : '') . ">Customer PhoneNumber</option> <!-- 搜尋條件 -->
                <option value='CustomerAddress' " . ($searchColumn === 'CustomerAddress' ? 'selected' : '') . ">Customer Address</option>  <!-- 搜尋條件 -->
            </select> <!-- 搜尋條件 -->
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'> <!-- 搜尋框 -->
            
            <button type='submit' class='btn btn-primary'>搜尋</button> <!-- 搜尋按鈕 -->
            <a href='index.php?Act=300&resultsPerPage=$resultsPerPage' class='btn btn-secondary'>顯示所有資料</a> <!-- 顯示所有資料按鈕 -->
            <a href='index.php?Act=320&resultsPerPage=$resultsPerPage' class='btn btn-success'>新增顧客</a> <!-- 新增按鈕 -->
        </form>

        <form id='deleteForm' method='post' action='index.php?Act=335' onsubmit='return confirmDelete();'> <!-- 刪除表單 -->
            <input type='hidden' name='resultsPerPage' value='$resultsPerPage'> <!-- 隱藏欄位，傳送每頁顯示筆數 -->
            <table class=\"table table-bordered table-hover\" style='width: 100%;'> <!-- 顯示顧客列表 -->
            <thead> <!-- 顯示顧客列表 -->
                <tr> <!-- 顯示顧客列表 -->
                    <th style='text-align: center; vertical-align: middle;' width=60px>全選<br><input type='checkbox' id='selectAll'></th>
                    <th style='text-align: center; vertical-align: middle;' width=auto><a href='?Act=300&sort=$nextSortOrder&sortColumn=CustomerID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>CustomerID</a></th> <!-- 顧客編號 -->
                    <th style='text-align: center; vertical-align: middle;' width=auto><a href='?Act=300&sort=" . ($sortColumn === 'CustomerName' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=CustomerName&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>Customer<br>Name</a></th> <!-- 顧客姓名 -->
                    <th style='text-align: center; vertical-align: middle;' width=auto>Customer<br>PhoneNumber</th> <!-- 顧客電話 -->
                    <th style='text-align: center; vertical-align: middle;' width=auto>Customer<br>Address</th> <!-- 顧客地址 -->
                    <th style='text-align: center; vertical-align: middle;' width=75px>功能</th> <!-- 操作按鈕 -->
                </tr> <!-- 結束顯示顧客列表 -->
            </thead> <!-- 結束顯示顧客列表 -->
            <tbody> <!-- 顯示顧客資料 -->
    ";

    try {
        // 設定查詢條件
        $query = "SELECT * FROM Customer"; // 查詢所有顧客
        if ($searchValue) { 
            if (empty($searchColumn) || $searchColumn === 'all') { 
                // 當未選擇搜尋條件或選擇 "全部"
                $query .= " WHERE (
                    CustomerID LIKE :searchValue OR 
                    CustomerName LIKE :searchValue OR 
                    CustomerPhoneNumber LIKE :searchValue OR 
                    CustomerAddress LIKE :searchValue
                )";
            } else { 
                // 如果選擇了特定欄位
                $query .= " WHERE $searchColumn LIKE :searchValue";
            }
        }
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset"; // 排序與分頁

        $stmt = $pdo->prepare($query); // 準備查詢
        if ($searchValue) { // 綁定搜尋條件
            $stmt->bindValue(':searchValue', "%$searchValue%", PDO::PARAM_STR); // 綁定搜尋值
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT); // 綁定每頁顯示筆數
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // 綁定位移量
        $stmt->execute(); // 執行查詢

        $results = $stmt->fetchAll(); // 取得查詢結果
        if (count($results) > 0) { // 有查詢結果
            foreach ($results as $row) { // 逐筆顯示查詢結果
                echo " 
                <tr> <!-- 顯示顧客資料 -->
                    <td style='text-align: center;'><input type='checkbox' name='selectedCustomers[]' value='{$row['CustomerID']}'></td> <!-- 勾選框 -->
                    <td style='text-align: center;'>{$row['CustomerID']}</td> <!-- 顧客編號 -->
                    <td style='text-align: center;'>{$row['CustomerName']}</td> <!-- 顧客姓名 -->
                    <td style='text-align: center;'>{$row['CustomerPhoneNumber']}</td> <!-- 顧客電話 -->
                    <td style='text-align: center;'>{$row['CustomerAddress']}</td> <!-- 顧客地址 -->
                    <td style='text-align: center;'> <!-- 操作按鈕 -->
                        <a href='index.php?Act=340&id={$row['CustomerID']}&resultsPerPage=$resultsPerPage' class='btn btn-primary btn-sm'>編輯</a> <!-- 編輯按鈕 -->
                    </td> <!-- 結束操作按鈕 -->
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='6' style='text-align: center;'>查無資料</td></tr>"; // 查無資料
        }
    } catch (PDOException $e) { // 資料庫錯誤
        echo "<p>錯誤：" . $e->getMessage() . "</p>"; // 顯示錯誤訊息
    }

    echo "
            </tbody> <!-- 結束顯示顧客資料 -->
            </table> <!-- 結束顯示顧客列表 -->
            <div> <!-- 操作按鈕 -->
                <button type='submit' class='btn btn-danger'>刪除勾選的資料</button> <!-- 刪除按鈕 -->
            </div>
            <div style='margin-top: 15px; background-color: white; text-align: center;'> <!-- 分頁導航 -->
    ";

    // 分頁導航
    $totalQuery = "SELECT COUNT(*) FROM Customer"; // 計算總筆數
    if ($searchValue) { 
        if (empty($searchColumn) || $searchColumn === 'all') { 
            $totalQuery .= " WHERE (
                CustomerID LIKE :searchValue OR 
                CustomerName LIKE :searchValue OR 
                CustomerPhoneNumber LIKE :searchValue OR 
                CustomerAddress LIKE :searchValue
            )";
        } else { 
            $totalQuery .= " WHERE $searchColumn LIKE :searchValue";
        }
    }

    $totalStmt = $pdo->prepare($totalQuery); // 準備計算總筆數
    if ($searchValue) { // 綁定搜尋條件
        $totalStmt->bindValue(':searchValue', "%$searchValue%", PDO::PARAM_STR); // 綁定搜尋值
    }
    $totalStmt->execute(); // 執行計算總筆數
    $totalResults = $totalStmt->fetchColumn(); // 取得總筆數
    $totalPages = ceil($totalResults / $resultsPerPage); // 計算總頁數

    if ($totalPages > 1) {
        echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>"; // 分頁導航
        echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>"; // 分頁按鈕
        if ($page > 1) { // 顯示上一頁按鈕
            echo "<li class='page-item'><a class='page-link' href='?Act=300&page=" . ($page - 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>"; // 上一頁按鈕
        }
        for ($i = 1; $i <= $totalPages; $i++) { // 顯示頁數按鈕
            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=300&page=$i&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>"; // 頁數按鈕
        }
        if ($page < $totalPages) { // 顯示下一頁按鈕
            echo "<li class='page-item'><a class='page-link' href='?Act=300&page=" . ($page + 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>"; // 下一頁按鈕
        }
        echo "</ul></nav>"; // 結束分頁按鈕
    }

    echo "
            </div> <!-- 結束分頁導航 -->
        </form> <!-- 結束刪除表單 -->
    </div> <!-- 結束顯示顧客列表 -->
    ";
} else { 
    echo "<p style='text-align:center; color:red;'>權限不足!</p>"; // 權限不足
}
?>

<script> <!-- 開始 JavaScript 區塊 -->
document.getElementById('selectAll').addEventListener('click', function(event) { // 全選/取消全選
    const checkboxes = document.querySelectorAll('input[name="selectedCustomers[]"]')
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked); // 勾選框
});

function confirmDelete() { // 確認刪除
    const checkboxes = document.querySelectorAll('input[name="selectedCustomers[]"]:checked'); // 勾選框
    if (checkboxes.length === 0) { // 未選擇任何顧客
        alert('未選擇任何顧客！'); // 未選擇任何顧客
        return false; // 取消提交
    }
    return confirm('確定要刪除選中的顧客嗎？'); // 確認刪除
}
</script> <!-- 結束 JavaScript 區塊 -->
