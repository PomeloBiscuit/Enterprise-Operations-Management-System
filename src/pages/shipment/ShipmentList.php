<?php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}
if (can_view_business_data()) {
    $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';
    $nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc';
    $sortColumn = isset($_GET['sortColumn']) ? $_GET['sortColumn'] : 'OrderID';
    $searchColumn = isset($_POST['searchColumn']) ? $_POST['searchColumn'] : (isset($_GET['searchColumn']) ? $_GET['searchColumn'] : '');
    $searchValue = isset($_POST['searchValue']) ? $_POST['searchValue'] : (isset($_GET['searchValue']) ? $_GET['searchValue'] : '');
    $resultsPerPage = isset($_POST['resultsPerPage']) ? intval($_POST['resultsPerPage']) : (isset($_GET['resultsPerPage']) ? intval($_GET['resultsPerPage']) : 10);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $resultsPerPage;

    $L_title = t('shipment.list.title');
    $L_perPage = t('common.search.per_page');
    $L_pickColumn = t('common.search.pick_column');
    $L_allColumns = t('common.search.all_columns');
    $L_searchPh = t('common.search.placeholder');
    $L_search = t('common.search');
    $L_showAll = t('common.show_all');
    $L_addShipment = t('shipment.add.title');
    $L_status = t('shipment.field.status');
    $L_action = t('common.action');
    $L_edit = t('common.edit');
    $L_deleteSelected = t('common.delete_selected');
    $L_noData = t('common.no_data');
    $L_statusDone = t('shipment.status.done');
    $L_statusPending = t('shipment.status.pending');

    echo "
    <div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
        <h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>$L_title</h3><hr>

        <!-- 搜尋框和新增按鈕 -->
        <form method='post' action='' style='display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;'>
            <div style='display: flex; align-items: center; gap: 10px;'>
                <label for='resultsPerPage' style='margin-right: 10px; text-align: center; align-self: center;'>$L_perPage</label>
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
                <option value=''>$L_pickColumn</option>
                <option value='all' " . ($searchColumn === 'all' ? 'selected' : '') . ">$L_allColumns</option>
                <option value='EmployeeID' " . ($searchColumn === 'EmployeeID' ? 'selected' : '') . ">EmployeeID</option>
                <option value='OrderID' " . ($searchColumn === 'OrderID' ? 'selected' : '') . ">OrderID</option>
                <option value='TrackingNumber' " . ($searchColumn === 'TrackingNumber' ? 'selected' : '') . ">TrackingNumber</option>
                <option value='ShipMethod' " . ($searchColumn === 'ShipMethod' ? 'selected' : '') . ">ShipMethod</option>
                <option value='status' " . ($searchColumn === 'status' ? 'selected' : '') . ">$L_status</option>
            </select>
            <input type='text' name='searchValue' placeholder='$L_searchPh' class='form-control' value='$searchValue' style='max-width: 300px;'>

            <button type='submit' class='btn btn-primary'>$L_search</button>
            <a href='index.php?Act=470' class='btn btn-secondary'>$L_showAll</a>
            <a href='index.php?Act=480' class='btn btn-success'>$L_addShipment</a>
        </form>

        <form id='deleteForm' method='post' action='index.php?Act=510' onsubmit='return confirmDelete();'>
            <div class=\"table-responsive\">
            <table class=\"table table-bordered table-hover\" style='width: 100%;'>
            <thead>
                <tr>
                    <th style='text-align: center;'><input type='checkbox' id='selectAll'></th>
                    <th style='text-align: center;'><a href='?Act=470&sort=$nextSortOrder&sortColumn=EmployeeID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>EmployeeID</a></th>
                    <th style='text-align: center;'><a href='?Act=470&sort=$nextSortOrder&sortColumn=OrderID&searchColumn=$searchColumn&searchValue=$searchValue&resultsPerPage=$resultsPerPage'>OrderID</a></th>
                    <th style='text-align: center;'>ShipDate</th>
                    <th style='text-align: center;'>TrackingNumber</th>
                    <th style='text-align: center;'>ShipMethod</th>
                    <th style='text-align: center;'>$L_status</th>
                    <th style='text-align: center;' width=160>$L_action</th>
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
                $status = isset($row['status']) ? ($row['status'] ? $L_statusDone : $L_statusPending) : $L_statusPending;
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
                        <a href='index.php?Act=490&id={$row['ShipmentID']}' class='btn btn-primary btn-sm'>$L_edit</a>
                    </td>
                </tr>
                ";
            }
        } else {
            echo "<tr><td colspan='8' style='text-align: center;'>$L_noData</td></tr>";
        }
    } catch (PDOException $e) {
        echo "<p>" . t('common.error_prefix') . $e->getMessage() . "</p>";
    }

    echo "
            </tbody>
            </table>
            </div>
            <button type='submit' class='btn btn-danger'>$L_deleteSelected</button>
        </form>
    </div>
    ";
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
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
        alert(<?php echo json_encode(t('shipment.none_selected')); ?>);
        return false;
    }
    return confirm(<?php echo json_encode(t('shipment.confirm_delete')); ?>);
}
</script>
