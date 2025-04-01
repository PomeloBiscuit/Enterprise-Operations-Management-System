<?php
if ($_SESSION["admlimit"] > 0) {
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc';
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'prikey';
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : '');
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : '');
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 10);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $resultsPerPage;

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>使用者列表</h3><hr>

        <!-- 搜尋框和新增按鈕 -->
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'>
            <div style='display: flex; align-items: center; gap: 10px;'>
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label>
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;' onchange='this.form.submit()'>
                    <option value='5' " . ($resultsPerPage === 5 ? 'selected' : '') . ">5</option>
                    <option value='10' " . ($resultsPerPage === 10 ? 'selected' : '') . ">10</option>
                    <option value='15' " . ($resultsPerPage === 15 ? 'selected' : '') . ">15</option>
                    <option value='20' " . ($resultsPerPage === 20 ? 'selected' : '') . ">20</option>
                    <option value='25' " . ($resultsPerPage === 25 ? 'selected' : '') . ">25</option>
                    <option value='50' " . ($resultsPerPage === 50 ? 'selected' : '') . ">50</option>
                </select>
            </div>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <select name='searchColumn' class='form-select' style='max-width: 200px;'>
                <option value=''>選擇搜尋條件</option>
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">全部</option>
                <option value='prikey' " . ($searchColumn === 'prikey' ? 'selected' : '') . ">主索引</option>
                <option value='name' " . ($searchColumn === 'name' ? 'selected' : '') . ">姓名</option>
                <option value='id' " . ($searchColumn === 'id' ? 'selected' : '') . ">帳號</option>
                <option value='email' " . ($searchColumn === 'email' ? 'selected' : '') . ">電子郵件</option>
            </select>
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>
            
            <button type='submit' class='btn btn-primary'>搜尋</button>
            <a href='index.php?Act=110' class='btn btn-secondary'>顯示所有資料</a>
            <a href='index.php?Act=140' class='btn btn-success'>新增使用者</a>
        </form>

        <form id='deleteForm' method='post' action='UserDelBatch.php' onsubmit='return confirmDelete();'>
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
            <thead>
                <tr>
                    <th style='text-align: center; width: 60px;'>全選<br><input type='checkbox' id='selectAll'></th>
                    <th style='text-align: center; width: auto;'><a href='?Act=110&sort=$nextSortOrder&sortColumn=prikey&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>主索引</a></th>
                    <th style='text-align: center; width: auto;'><a href='?Act=110&sort=" . ($sortColumn === 'name' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=name&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>姓名</a></th>
                    <th style='text-align: center; width: auto;'><a href='?Act=110&sort=" . ($sortColumn === 'id' && $sortOrder === 'ASC' ? 'desc' : 'asc') . "&sortColumn=id&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>帳號</a></th>
                    <th style='text-align: center; width: auto;'>電子郵件</th>
                    <th style='text-align: center; width: auto;'>電話</th>
                    <th style='text-align: center; width: auto;'>行動電話</th>
                    <th style='text-align: center; width: auto;'>啟用</th>
                    <th style='text-align: center; width: 75px;'>功能</th>
                </tr>
            </thead>
            <tbody>
    ";

    try {
        // 設定查詢條件
        $query = "
            SELECT *
            FROM User
        ";
        if ($searchColumn && $searchValue) {
            if ($searchColumn === 'all') {
                $query .= " WHERE (name LIKE :searchValue OR id LIKE :searchValue OR email LIKE :searchValue)";
            } else {
                $query .= " WHERE $searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $query .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($query);
        if ($searchColumn && $searchValue) {
            $stmt->bindValue(':searchValue', "%$searchValue%");
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();
        if (count($results) > 0) {
            foreach ($results as $row) {
                echo "
                <tr>
                    <td style='text-align: center;'><input type='checkbox' name='selectedUsers[]' value='{$row['prikey']}'></td>
                    <td style='text-align: center;'>{$row['prikey']}</td>
                    <td style='text-align: center;'>{$row['name']}</td>
                    <td style='text-align: center;'>{$row['id']}</td>
                    <td style='text-align: center;'>{$row['email']}</td>
                    <td style='text-align: center;'>{$row['phone']}</td>
                    <td style='text-align: center;'>{$row['phonem']}</td>
                    <td style='text-align: center;'>{$row['enabled']}</td>
                    <td style='text-align: center;'>
                        <a href='index.php?Act=120&EK={$row['prikey']}' class='btn btn-primary btn-sm'>編輯</a>
                    </td>
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='9' style='text-align: center;'>查無資料</td></tr>";
        }

        // 計算總頁數
        $countQuery = "
            SELECT COUNT(*) 
            FROM User
        ";
        if ($searchColumn && $searchValue) {
            if ($searchColumn === 'all') {
                $countQuery .= " WHERE (name LIKE :searchValue OR id LIKE :searchValue OR email LIKE :searchValue)";
            } else {
                $countQuery .= " WHERE $searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $countQuery .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }

        $countStmt = $pdo->prepare($countQuery);
        if ($searchColumn && $searchValue) {
            $countStmt->bindValue(':searchValue', "%$searchValue%");
        }
        $countStmt->execute();
        $totalResults = $countStmt->fetchColumn();
        $totalPages = ceil($totalResults / $resultsPerPage);

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
    echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>";
    echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>";
    if ($page > 1) {
        echo "<li class='page-item'><a class='page-link' href='?Act=110&page=" . ($page - 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>";
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=110&page=$i&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>";
    }
    if ($page < $totalPages) {
        echo "<li class='page-item'><a class='page-link' href='?Act=110&page=" . ($page + 1) . "&sort=$sortOrder&sortColumn=$sortColumn&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>";
    }
    echo "</ul></nav>";

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
    const checkboxes = document.querySelectorAll('input[name="selectedUsers[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);
});

function confirmDelete() {
    const checkboxes = document.querySelectorAll('input[name="selectedUsers[]"]:checked');
    if (checkboxes.length === 0) {
        alert('未選擇任何使用者！');
        return false;
    }
    return confirm('確定要刪除選中的使用者嗎？');
}
</script>
