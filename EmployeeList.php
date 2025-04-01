<?php
if ($_SESSION["admlimit"] > 0) { // 判斷是否有登入
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; // 預設排序方式為 ASC
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc'; // 下一次排序方式
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'EmployeeID'; // 預設排序欄位為 EmployeeID
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : ''); // 預設搜尋欄位為空
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : ''); // 預設搜尋內容為空
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 5); // 預設顯示 5 筆資料
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1; // 預設顯示第 1 頁
    $offset = ($page - 1) * $resultsPerPage; // 計算位移量

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>員工列表</h3><hr>

        <!-- 搜尋框和新增按鈕 -->
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'>
            <div style='display: flex; align-items: center; gap: 10px;'>
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label> <!-- 顯示筆數標籤 -->
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;' onchange='this.form.submit()'>
                    <option value='5' " . ($resultsPerPage === 5 ? 'selected' : '') . ">5</option> <!-- 預設顯示 5 筆資料 -->
                    <option value='10' " . ($resultsPerPage === 10 ? 'selected' : '') . ">10</option> <!-- 選擇顯示 10 筆資料 -->
                    <option value='15' " . ($resultsPerPage === 15 ? 'selected' : '') . ">15</option> <!-- 選擇顯示 15 筆資料 -->
                    <option value='20' " . ($resultsPerPage === 20 ? 'selected' : '') . ">20</option> <!-- 選擇顯示 20 筆資料 -->
                    <option value='25' " . ($resultsPerPage === 25 ? 'selected' : '') . ">25</option> <!-- 選擇顯示 25 筆資料 -->
                    <option value='50' " . ($resultsPerPage === 50 ? 'selected' : '') . ">50</option> <!-- 選擇顯示 50 筆資料 -->
                </select>
            </div>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <select name='searchColumn' class='form-select' style='max-width: 200px;'>
                <option value=''>選擇搜尋條件</option>
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">全部</option>
                <option value='EmployeeID' " . ($searchColumn === 'EmployeeID' ? 'selected' : '') . ">Employee ID</option>
                <option value='EmployeeName' " . ($searchColumn === 'EmployeeName' ? 'selected' : '') . ">Employee Name</option>
            </select>
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>
            
            <button type='submit' class='btn btn-primary'>搜尋</button>
            <a href='index.php?Act=350&resultsPerPage=$resultsPerPage' class='btn btn-secondary'>顯示所有資料</a>
            <a href='index.php?Act=360&resultsPerPage=$resultsPerPage' class='btn btn-success'>新增員工</a>
        </form>

        <form id='deleteForm' method='post' action='index.php?Act=375' onsubmit='return confirmDelete();'>
            <input type='hidden' name='resultsPerPage' value='$resultsPerPage'>
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
            <thead>
                <tr>
                    <th style='text-align: center; vertical-align: middle; width: 60px;'>全選<br><input type='checkbox' id='selectAll'></th>
                    <th style='text-align: center; vertical-align: middle; width: auto;'><a href='?Act=350&sort=$nextSortOrder&sortColumn=EmployeeID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>EmployeeID</a></th>
                    <th style='text-align: center; vertical-align: middle; width: auto;'><a href='?Act=350&sort=" . ($sortColumn === 'EmployeeName' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=EmployeeName&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>EmployeeName</a></th>
                    <th style='text-align: center; vertical-align: middle; width: 75px;'>功能</th>
                </tr>
            </thead>
            <tbody>
    ";

    try {
        // 設定查詢條件
        $query = "SELECT * FROM Employee"; // 查詢所有欄位
        if ($searchValue) { // 有輸入搜尋內容
            if ($searchColumn === 'all' || empty($searchColumn)) { // 若選擇所有欄位或未選擇搜尋條件
                $query .= " WHERE (EmployeeID LIKE :searchValue OR EmployeeName LIKE :searchValue)";// 搜尋 EmployeeID 或 EmployeeName
            } else {
                $query .= " WHERE $searchColumn LIKE :searchValue"; // 搜尋指定欄位
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $query .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset"; // 排序、分頁

        $stmt = $pdo->prepare($query); // 準備查詢
        if ($searchValue) { // 綁定搜尋內容
            $stmt->bindValue(':searchValue', "%$searchValue%"); // 使用部分符合搜尋
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT); // 綁定每頁顯示筆數
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // 綁定位移量
        $stmt->execute(); // 執行查詢

        $results = $stmt->fetchAll(); // 取得查詢結果
        if (count($results) > 0) { // 有查詢到資料
            foreach ($results as $row) { // 逐筆顯示
                echo "
                <tr>
                    <td style='text-align: center;'><input type='checkbox' name='selectedEmployees[]' value='{$row['EmployeeID']}'></td>
                    <td style='text-align: center;'>{$row['EmployeeID']}</td>
                    <td style='text-align: center;'>{$row['EmployeeName']}</td>
                    <td style='text-align: center;'>
                        <a href='index.php?Act=380&id={$row['EmployeeID']}&resultsPerPage=$resultsPerPage' class='btn btn-primary btn-sm'>編輯</a>
                    </td>
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='4' style='text-align: center;'>查無資料</td></tr>";
        }
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }

    echo "
            </tbody>
            </table>
            <div>
                <button type='submit' class='btn btn-danger'>刪除勾選的資料</button>
            </div>
            <div style='margin-top: 15px; background-color: white; text-align: center;'>
    ";

    // 分頁導航
    $totalQuery = "SELECT COUNT(*) FROM Employee"; // 計算總筆數
    if ($searchValue) {
        if ($searchColumn === 'all' || empty($searchColumn)) {
            $totalQuery .= " WHERE (EmployeeID LIKE :searchValue OR EmployeeName LIKE :searchValue)"; // 搜尋 EmployeeID 或 EmployeeName
        } else {
            $totalQuery .= " WHERE $searchColumn LIKE :searchValue"; // 搜尋指定欄位
        }
    } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
        $totalQuery .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
    }

    $totalStmt = $pdo->prepare($totalQuery);
    if ($searchValue) {
        $totalStmt->bindValue(':searchValue', "%$searchValue%");
    }
    $totalStmt->execute();
    $totalResults = $totalStmt->fetchColumn();
    $totalPages = ceil($totalResults / $resultsPerPage);

    if ($totalPages > 1) {
        echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>";
        echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>";
        if ($page > 1) {
            echo "<li class='page-item'><a class='page-link' href='?Act=350&page=" . ($page - 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>";
        }
        for ($i = 1; $i <= $totalPages; $i++) {
            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=350&page=$i&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>";
        }
        if ($page < $totalPages) {
            echo "<li class='page-item'><a class='page-link' href='?Act=350&page=" . ($page + 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>";
        }
        echo "</ul></nav>";
    }

    echo "
            </div>
        </form>
    </div>
    ";
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>

<script>
document.getElementById('selectAll').addEventListener('click', function(event) {
    const checkboxes = document.querySelectorAll('input[name="selectedEmployees[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);
});

function confirmDelete() {
    const checkboxes = document.querySelectorAll('input[name="selectedEmployees[]"]:checked');
    if (checkboxes.length === 0) {
        alert('未選擇任何員工！');
        return false;
    }
    return confirm('確定要刪除選中的員工嗎？');
}
</script>
