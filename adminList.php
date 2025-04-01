<?php
if ($_SESSION["admlimit"] > 0) {
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc';
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : '');
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : '');
    // 若搜尋欄位為 limited，且使用者輸入是、否，轉成 1 或 0
    if ($searchColumn === 'limited' && ($searchValue === '是' || $searchValue === '否')) {
        $searchValue = ($searchValue === '是') ? 1 : 0;
    }
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 5);
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
                <option value='prikey' " . ($searchColumn === 'prikey' ? 'selected' : '') . ">UserID</option>
                <option value='name' " . ($searchColumn === 'name' ? 'selected' : '') . ">姓名</option>
                <option value='id' " . ($searchColumn === 'id' ? 'selected' : '') . ">帳號</option>
                <option value='email' " . ($searchColumn === 'email' ? 'selected' : '') . ">電子郵件</option>
                <option value='phone' " . ($searchColumn === 'phone' ? 'selected' : '') . ">固定電話</option>
                <option value='phonem' " . ($searchColumn === 'phonem' ? 'selected' : '') . ">行動電話</option>
                <option value='limited' " . ($searchColumn === 'limited' ? 'selected' : '') . ">管理員身份</option>
            </select>
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>
            
            <button type='submit' class='btn btn-primary'>搜尋</button>
            <a href='index.php?Act=110&resultsPerPage=$resultsPerPage' class='btn btn-secondary'>顯示所有資料</a>
            <button type='button' class='btn btn-success' onclick=\"location.href='index.php?Act=140&resultsPerPage=$resultsPerPage';\" " . ($_SESSION["admlimit"] != 1 ? 'disabled' : '') . ">新增人員</button>
        </form>

        <form id='deleteForm' method='post' action='deleteSelectedUsers.php' onsubmit='return confirmDelete();'>
            <input type='hidden' name='resultsPerPage' value='$resultsPerPage'>
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
            <thead>
                <tr>
                    <th style='width: 60px; text-align: center; vertical-align: middle;'>全選<br><input type='checkbox' id='selectAll' " . ($_SESSION["admlimit"] != 1 ? 'disabled' : '') . "></th>
                    <th style='width: 60px; text-align: center; vertical-align: middle;'><a href='?Act=110&sort=$nextSortOrder&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>UserID</a></th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>姓名</th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>帳號</th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>固定電話</th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>行動電話</th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>電子郵件</th>
                    <th style='width: auto; text-align: center; vertical-align: middle;'>管理員身份</th>
                    <th style='width: 75px; text-align: center; vertical-align: middle;'>功能</th>
                </tr>
            </thead>
            <tbody>
    ";

    try {
        // 設定查詢條件
        $query = "SELECT * FROM User WHERE enabled > 0";
        if ($searchValue !== '') {
            if (empty($searchColumn) || $searchColumn === 'all') {
                // 搜尋所有相關欄位
                $query .= " AND (
                    prikey LIKE :searchValue OR
                    name LIKE :searchValue OR
                    id LIKE :searchValue OR
                    email LIKE :searchValue OR
                    phone LIKE :searchValue OR
                    phonem LIKE :searchValue OR
                    CAST(limited AS CHAR) LIKE :searchValue
                )";
            } elseif ($searchColumn === 'limited') {
                // 精確搜尋 limited 欄位
                $query .= " AND $searchColumn = :searchValue";
            } else {
                // 搜尋特定欄位
                $query .= " AND $searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $query .= " AND 1=0"; // 當選擇搜尋條件但未輸入搜尋值時，強制無結果
        }
        $query .= " ORDER BY prikey $sortOrder LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($query);
        if ($searchValue !== '') {
            if ($searchColumn === 'limited') {
                $stmt->bindValue(':searchValue', $searchValue, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':searchValue', "%$searchValue%", PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();
        if (count($results) > 0) {
            foreach ($results as $row) {
                $isAdmin = $row['limited'] == 1 ? '是' : '否';
                $disableButtons = ($_SESSION["admlimit"] != 1) ? 'disabled' : '';
                $editLink = ($_SESSION["admlimit"] == 1) ? "href='index.php?Act=120&EK={$row['prikey']}&resultsPerPage=$resultsPerPage'" : '';
                echo "
                <tr>
                    <td style='text-align: center;'><input type='checkbox' name='selectedUsers[]' value='{$row['prikey']}' $disableButtons></td>
                    <td style='text-align: center;'>{$row['prikey']}</td>
                    <td style='text-align: center;'>{$row['name']}</td>
                    <td style='text-align: center;'>{$row['id']}</td>
                    <td style='text-align: center;'>{$row['phone']}</td>
                    <td style='text-align: center;'>{$row['phonem']}</td>
                    <td style='text-align: center;'>{$row['email']}</td>
                    <td style='text-align: center;'>$isAdmin</td>
                    <td style='text-align: center;'>
                        <div style='display: flex; justify-content: center; gap: 10px;'>
                            <a $editLink class='btn btn-primary btn-sm' $disableButtons>修改</a>
                        </div>
                    </td>
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='9' style='text-align: center;'>查無資料</td></tr>";
        }

        // 計算總頁數
        $countQuery = "SELECT COUNT(*) FROM User WHERE enabled > 0";
        if ($searchValue !== '') {
            if (empty($searchColumn) || $searchColumn === 'all') {
                // 計算符合搜尋值的所有欄位
                $countQuery .= " AND (
                    prikey LIKE :searchValue OR
                    name LIKE :searchValue OR
                    id LIKE :searchValue OR
                    email LIKE :searchValue OR
                    phone LIKE :searchValue OR
                    phonem LIKE :searchValue OR
                    CAST(limited AS CHAR) LIKE :searchValue
                )";
            } elseif ($searchColumn === 'limited') {
                $countQuery .= " AND $searchColumn = :searchValue"; // 精確匹配 limited
            } else {
                $countQuery .= " AND $searchColumn LIKE :searchValue"; // 搜尋特定欄位
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $countQuery .= " AND 1=0"; // 當選擇搜尋條件但未輸入搜尋值時，強制無結果
        }

        $countStmt = $pdo->prepare($countQuery);
        if ($searchValue !== '') {
            if ($searchColumn === 'limited') {
                $countStmt->bindValue(':searchValue', $searchValue, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue(':searchValue', "%$searchValue%", PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $totalResults = $countStmt->fetchColumn();
        $totalPages = $totalResults > 0 ? ceil($totalResults / $resultsPerPage) : 1; // 確保 totalPages 至少為 1

    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }

    echo "
            </tbody>
            </table>
            <div>
                <button type='submit' class='btn btn-danger' " . ($_SESSION["admlimit"] != 1 ? 'disabled' : '') . ">刪除勾選的資料</button>
            </div>
            <div style='margin-top: 15px; background-color: white; text-align: center;'>
    ";

    // 分頁導航
    if ($totalPages > 1) {
        echo "<nav aria-label='Page navigation' style='display: flex; justify-content: center; background-color: white;'>";
        echo "<ul class='pagination justify-content-center' style='background-color: transparent;'>";
        if ($page > 1) {
            echo "<li class='page-item'><a class='page-link' href='?Act=110&page=" . ($page - 1) . "&sort=$sortOrder&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>上一頁</a></li>";
        }
        for ($i = 1; $i <= $totalPages; $i++) {
            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?Act=110&page=$i&sort=$sortOrder&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>$i</a></li>";
        }
        if ($page < $totalPages) {
            echo "<li class='page-item'><a class='page-link' href='?Act=110&page=" . ($page + 1) . "&sort=$sortOrder&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>下一頁</a></li>";
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
    const checkboxes = document.querySelectorAll('input[name="selectedUsers[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);
});

function confirmDelete() {
    const checkboxes = document.querySelectorAll('input[name="selectedUsers[]"]:checked');
    if (checkboxes.length === 0) {
        alert('未選擇任何使用者！');
        return false;
    }
    const selfDelete = Array.from(checkboxes).some(checkbox => checkbox.value === '<?php echo $_SESSION["admprikey"]; ?>');
    if (selfDelete) {
        return confirm('執行此操作會刪除此帳號，您確定要繼續執行嗎？');
    }
    return confirm('確定要刪除選中的使用者嗎？');
}
</script>
