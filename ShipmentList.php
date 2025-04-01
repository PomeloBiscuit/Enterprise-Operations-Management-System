<?php
if ($_SESSION["admlimit"] > 0) {
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc';
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'OrderID';
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : '');
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : '');
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 10);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $resultsPerPage;

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>出貨紀錄列表</h3><hr>

        <!-- 搜尋框和新增按鈕 -->
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'>
            <div style='display: flex; align-items: center; gap: 10px;'>
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>顯示筆數</label>
                <select name='resultsPerPage' id='resultsPerPage' class='form-select' style='max-width: 100px;'>
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
                <option value='EmployeeID' " . ($searchColumn === 'EmployeeID' ? 'selected' : '') . ">EmployeeID</option>
                <option value='OrderID' " . ($searchColumn === 'OrderID' ? 'selected' : '') . ">OrderID</option>
                <option value='TrackingNumber' " . ($searchColumn === 'TrackingNumber' ? 'selected' : '') . ">TrackingNumber</option>
                <option value='ShipMethod' " . ($searchColumn === 'ShipMethod' ? 'selected' : '') . ">ShipMethod</option>
                <option value='status' " . ($searchColumn === 'status' ? 'selected' : '') . ">狀態</option>
            </select>
            <input type='text' name='searchValue' placeholder='輸入搜尋內容' class='form-control' value='$searchValue' style='max-width: 300px;'>
            
            <button type='submit' class='btn btn-primary'>搜尋</button>
            <a href='index.php?Act=470' class='btn btn-secondary'>顯示所有資料</a>
            <a href='index.php?Act=480' class='btn btn-success'>新增出貨紀錄</a>
        </form>

        <form id='deleteForm' method='post' action='ShipmentDelBatch.php' onsubmit='return confirmDelete();'>
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
            <thead>
                <tr>
                    <th style='text-align: center;'><input type='checkbox' id='selectAll'></th>
                    <th style='text-align: center;'><a href='?Act=470&sort=$nextSortOrder&sortColumn=EmployeeID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>EmployeeID</a></th>
                    <th style='text-align: center;'><a href='?Act=470&sort=$nextSortOrder&sortColumn=OrderID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>OrderID</a></th>
                    <th style='text-align: center;'>ShipDate</th>
                    <th style='text-align: center;'>TrackingNumber</th>
                    <th style='text-align: center;'>ShipMethod</th>
                    <th style='text-align: center;'>狀態</th>
                    <th style='text-align: center;' width=160>功能</th>
                </tr>
            </thead>
            <tbody>
    ";

    try {
        // 設定查詢條件
        $query = "SELECT * FROM Shipment";
        if ($searchColumn && $searchValue) {
            if ($searchColumn === 'all') {
                $query .= " WHERE (EmployeeID LIKE :searchValue OR OrderID LIKE :searchValue OR TrackingNumber LIKE :searchValue OR ShipMethod LIKE :searchValue)";
            } elseif ($searchColumn === 'status') {
                $query .= " WHERE status = :searchValue";
            } else {
                $query .= " WHERE $searchColumn LIKE :searchValue";
            }
        } elseif ($searchColumn && !$searchValue && $searchColumn !== 'all') {
            $query .= " WHERE 1=0"; // 當選擇搜尋條件但未輸入搜尋內容時，強制查無資料
        }
        $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($query);
        if ($searchColumn && $searchValue) {
            if ($searchColumn === 'status') {
                $stmt->bindValue(':searchValue', $searchValue === '完成' ? 1 : 0, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':searchValue', "%$searchValue%");
            }
        }
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();
        if (count($results) > 0) {
            foreach ($results as $row) {
                $status = isset($row['status']) ? ($row['status'] ? '完成' : '未完成') : '未完成';
                echo "
                <tr>
                    <td style='text-align: center;'><input type='checkbox' name='selectedShipments[]' value='{$row['ShipmentID']}'></td>
                    <td style='text-align: center;'>{$row['EmployeeID']}</td>
                    <td style='text-align: center;'>{$row['OrderID']}</td>
                    <td style='text-align: center;'>{$row['ShipDate']}</td>
                    <td style='text-align: center;'>{$row['TrackingNumber']}</td>
                    <td style='text-align: center;'>{$row['ShipMethod']}</td>
                    <td style='text-align: center;'>$status</td>
                    <td style='text-align: center;'>
                        <a href='index.php?Act=490&id={$row['ShipmentID']}' class='btn btn-primary btn-sm'>編輯</a>
                    </td>
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='8' style='text-align: center;'>查無資料</td></tr>";
        }
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }

    echo "
            </tbody>
            </table>
            <button type='submit' class='btn btn-danger'>刪除勾選的資料</button>
        </form>
    </div>
    ";
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>

<script>
document.getElementById('selectAll').addEventListener('click', function(event) {
    const checkboxes = document.querySelectorAll('input[name="selectedShipments[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);
});

function confirmDelete() {
    const checkboxes = document.querySelectorAll('input[name="selectedShipments[]"]:checked');
    if (checkboxes.length === 0) {
        alert('未選擇任何出貨紀錄！');
        return false;
    }
    return confirm('確定要刪除選中的出貨紀錄嗎？');
}
</script>
