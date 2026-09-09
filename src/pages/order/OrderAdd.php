<?php // OrderAdd.php
require_once __DIR__ . '/../../config.inc.php'; // 引入資料庫設定檔
require_once __DIR__ . '/../../auth.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>權限不足!</p>";
    exit;
}

function generateTrackingNumber() { // 產生追蹤號碼
    return 'TN' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 產生 6 位數的追蹤號碼
}

$orderID = null; // 訂單編號

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // 如果是 POST 請求
    try {
        // 檢查顧客ID與員工ID是否存在（產品改為多列，另外驗證）
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Customer WHERE CustomerID = :CustomerID");
        $stmt->execute([':CustomerID' => $_POST['CustomerID']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Invalid Customer ID");
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Employee WHERE EmployeeID = :EmployeeID");
        $stmt->execute([':EmployeeID' => $_POST['EmployeeID']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Invalid Employee ID");
        }

        // 訂單明細：每列一個產品 + 數量。重複產品合併數量（Contain 的 PK 是 OrderID+ProductID）。
        $postProductIDs = $_POST['ProductID'] ?? [];
        $postQuantities = $_POST['Quantity'] ?? [];
        if (!is_array($postProductIDs)) {
            $postProductIDs = [];
        }
        $lineItems = []; // ProductID => 合併後的數量
        foreach ($postProductIDs as $idx => $rawPid) {
            $pid = (int) $rawPid;
            $qty = (int) ($postQuantities[$idx] ?? 0);
            if ($pid <= 0) {
                continue; // 略過沒有選產品的空列
            }
            if ($qty <= 0) {
                throw new Exception("產品 $pid 的數量必須大於 0");
            }
            $lineItems[$pid] = ($lineItems[$pid] ?? 0) + $qty;
        }
        if (count($lineItems) === 0) {
            throw new Exception("訂單至少要有一項產品");
        }
        foreach (array_keys($lineItems) as $pid) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE ProductID = :ProductID");
            $stmt->execute([':ProductID' => $pid]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("Invalid Product ID: $pid");
            }
        }

        // 取得表單其餘欄位
        $CustomerID = $_POST['CustomerID'];
        $EmployeeID = $_POST['EmployeeID'];
        $OrderTime = $_POST['OrderTime'];
        $ShipDate = $_POST['ShipDate'];
        $TrackingNumber = $_POST['TrackingNumber'];
        $ShipMethod = $_POST['ShipMethod'];
        $resultsPerPage = $_POST['resultsPerPage'];

        // 訂單主檔 + 明細一起寫，任一失敗整批回滾
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO Orders (CustomerID, EmployeeID, OrderTime, ShipDate, TrackingNumber, ShipMethod)
            VALUES (:CustomerID, :EmployeeID, :OrderTime, :ShipDate, :TrackingNumber, :ShipMethod)
        ");
        $stmt->execute([
            ':CustomerID' => $CustomerID,
            ':EmployeeID' => $EmployeeID,
            ':OrderTime' => $OrderTime,
            ':ShipDate' => $ShipDate,
            ':TrackingNumber' => $TrackingNumber,
            ':ShipMethod' => $ShipMethod
        ]);
        $orderID = $pdo->lastInsertId(); // 取得最後新增的訂單編號

        $containStmt = $pdo->prepare("
            INSERT INTO Contain (OrderID, ProductID, Quantity) VALUES (:OrderID, :ProductID, :Quantity)
        ");
        foreach ($lineItems as $pid => $qty) {
            $containStmt->execute([':OrderID' => $orderID, ':ProductID' => $pid, ':Quantity' => $qty]);
        }
        $pdo->commit();

        header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage"); // 重新導向到訂單列表
        exit();
    } catch (Throwable $e) { // 例外處理（含 PDOException）
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else { // 如果是 GET 請求
    try { // 取得下一個訂單編號
        $stmt = $pdo->query("SELECT COALESCE((SELECT seq FROM sqlite_sequence WHERE name = 'Orders'), 0) + 1 AS AUTO_INCREMENT");
        $row = $stmt->fetch();
        $nextOrderID = $row['AUTO_INCREMENT'];
    } catch (PDOException $e) {
        echo "<p>Unable to retrieve order ID: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Fetch customers, products, and employees for selection
$customers = $pdo->query("SELECT CustomerID, CustomerName, CustomerPhoneNumber FROM Customer")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);

// 產品下拉選項的 HTML，初始列與 JS 動態新增列共用
ob_start();
foreach ($products as $product) {
    echo '<option value="' . (int) $product['ProductID'] . '">'
        . htmlspecialchars($product['ProductID'] . ' - ' . $product['ProductName'])
        . '</option>';
}
$productOptionsHtml = ob_get_clean();
?>  <!-- 結束 PHP 區塊 -->

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <h3 style='text-align: center; font-family: "Noto Sans TC", "Times New Roman", serif;'>新增訂單</h3><hr>
    <form action="OrderAdd.php" method="post" id="orderAddForm">
        <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>">
        <div class="form-group">
            <label>Order ID</label>
            <input type="text" id="orderID" class="form-control" value="<?php echo isset($nextOrderID) ? $nextOrderID : ''; ?>" disabled>
        </div>
        <div class="form-group">
            <label for="EmployeeID">Employee ID</label>
            <input type="text" id="employeeSearch" class="form-control" placeholder="輸入Employee ID or Name">
            <select name="EmployeeID" id="EmployeeID" class="form-control mt-2">
                <option value="">選擇Employee</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="CustomerID">Customer ID</label>
            <input type="text" id="customerSearch" class="form-control" placeholder="搜尋 Customer ID, Name or Phone">
            <select name="CustomerID" id="customerID" class="form-control mt-2" required>
                <option value="">選擇Customer</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['CustomerID']; ?>"><?php echo $customer['CustomerID'] . ' - ' . $customer['CustomerName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>訂單明細（產品與數量）</label>
            <table class="table table-sm" style="width: 100%;">
                <thead>
                    <tr>
                        <th>產品</th>
                        <th style="width: 120px;">數量</th>
                        <th style="width: 90px;">操作</th>
                    </tr>
                </thead>
                <tbody id="lineItemsBody"></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addLineItem">＋ 新增一列</button>
        </div>

        <!-- 動態新增列用的樣板；不會被送出 -->
        <template id="lineItemTemplate">
            <tr class="line-item">
                <td>
                    <select name="ProductID[]" class="form-control line-product">
                        <option value="">選擇Product</option>
                        <?php echo $productOptionsHtml; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="Quantity[]" class="form-control line-qty" min="1" step="1" value="1">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-line">刪除</button>
                </td>
            </tr>
        </template>

        <div class="form-group">
            <label>Order Time</label>
            <input type="datetime-local" name="OrderTime" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Ship Date</label>
            <input type="date" name="ShipDate" class="form-control">
        </div>
        <div class="form-group">
            <label>Tracking Number</label>
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo generateTrackingNumber(); ?>" readonly>
        </div>
        <div class="form-group">
            <label>Ship Method</label>
            <select name="ShipMethod" class="form-control">
                <option value="Air">Air</option>
                <option value="Sea">Sea</option>
                <option value="Land">Land</option>
            </select>
        </div>
        <br>
        <div style="text-align: center;">
            <a href="index.php?Act=430&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="reset" class="btn btn-warning text-white">清除</button>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="submit" class="btn btn-primary">新增</button>
        </div>
    </form>
</div>

<script src="js/jquery-3.6.0.min.js"></script>  <!-- 引入 jQuery -->
<script>
$(document).ready(function() {
    // ---- 訂單明細：動態增減列 ----
    var lineItemTemplate = document.getElementById('lineItemTemplate');
    var lineItemsBody = document.getElementById('lineItemsBody');

    function addLineItem() {
        lineItemsBody.appendChild(lineItemTemplate.content.cloneNode(true));
    }
    addLineItem(); // 一開始就給一列

    $('#addLineItem').on('click', addLineItem);

    $(lineItemsBody).on('click', '.remove-line', function() {
        if (lineItemsBody.querySelectorAll('.line-item').length > 1) {
            $(this).closest('.line-item').remove();
        } else {
            alert('至少要保留一列明細');
        }
    });

    $('#orderAddForm').on('submit', function(e) {
        var hasValidLine = false;
        lineItemsBody.querySelectorAll('.line-item').forEach(function(row) {
            var pid = row.querySelector('.line-product').value;
            var qty = parseInt(row.querySelector('.line-qty').value, 10);
            if (pid && qty > 0) {
                hasValidLine = true;
            }
        });
        if (!hasValidLine) {
            e.preventDefault();
            alert('請至少選擇一項產品並填入大於 0 的數量');
        }
    });

    // ---- 員工可搜尋下拉 ----
    $('#employeeSearch').on('input', function() {
        var searchValue = $(this).val().toLowerCase();
        $('#EmployeeID option').each(function() {
            if ($(this).text().toLowerCase().includes(searchValue)) {
                $(this).prop('selected', true);
                return false;
            }
        });
    });

    // ---- 顧客可搜尋下拉 ----
    $('#customerSearch').on('input', function() {
        var searchValue = $(this).val().toLowerCase();
        $('#customerID option').each(function() {
            if ($(this).text().toLowerCase().includes(searchValue)) {
                $(this).prop('selected', true);
                return false;
            }
        });
    });

    $('#EmployeeID').on('change', function() {
        var selectedText = $('#EmployeeID option:selected').text();
        $('#employeeSearch').val(selectedText.split(' - ')[0]);
    });

    $('#customerID').on('change', function() {
        var selectedText = $('#customerID option:selected').text();
        $('#customerSearch').val(selectedText.split(' - ')[0]);
    });

    // 自動隱藏 ShipDate / OrderTime 的原生選擇器
    var shipDateInput = document.querySelector('input[name="ShipDate"]');
    shipDateInput.addEventListener('change', function() { shipDateInput.blur(); });
    var orderTimeInput = document.querySelector('input[name="OrderTime"]');
    orderTimeInput.addEventListener('change', function() { orderTimeInput.blur(); });
});

document.getElementById('customerSearch').addEventListener('input', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($customers); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.CustomerName.toLowerCase().includes(searchValue) ||
            option.CustomerPhoneNumber.toLowerCase().includes(searchValue) ||
            option.CustomerID.toString().includes(searchValue);
    });
    var customerSelect = document.getElementById('customerID');
    customerSelect.innerHTML = '<option value="">選擇顧客</option>';
    filteredOptions.forEach(function(option) {
        var opt = document.createElement('option');
        opt.value = option.CustomerID;
        opt.textContent = option.CustomerID + ' - ' + option.CustomerName;
        customerSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        customerSelect.value = filteredOptions[0].CustomerID;
    }
});

document.getElementById('employeeSearch').addEventListener('input', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($employees); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.EmployeeName.toLowerCase().includes(searchValue) ||
            option.EmployeeID.toString().includes(searchValue);
    });
    var employeeSelect = document.getElementById('EmployeeID');
    employeeSelect.innerHTML = '<option value="">選擇員工</option>';
    filteredOptions.forEach(function(option) {
        var opt = document.createElement('option');
        opt.value = option.EmployeeID;
        opt.textContent = option.EmployeeID + ' - ' + option.EmployeeName;
        employeeSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        employeeSelect.value = filteredOptions[0].EmployeeID;
    }
});

document.getElementById('customerSearch').addEventListener('blur', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($customers); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.CustomerName.toLowerCase().includes(searchValue) ||
            option.CustomerPhoneNumber.toLowerCase().includes(searchValue) ||
            option.CustomerID.toString().includes(searchValue);
    });
    if (filteredOptions.length === 1) {
        document.getElementById('customerID').value = filteredOptions[0].CustomerID;
    } else {
        var exactMatches = options.filter(function(option) {
            return option.CustomerID.toString() === searchValue || option.CustomerName.toLowerCase() === searchValue;
        });
        document.getElementById('customerID').value = exactMatches.length === 1 ? exactMatches[0].CustomerID : '';
    }
});

document.getElementById('employeeSearch').addEventListener('blur', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($employees); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.EmployeeName.toLowerCase().includes(searchValue) ||
            option.EmployeeID.toString().includes(searchValue);
    });
    if (filteredOptions.length === 1) {
        document.getElementById('EmployeeID').value = filteredOptions[0].EmployeeID;
    } else {
        var exactMatches = options.filter(function(option) {
            return option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue;
        });
        document.getElementById('EmployeeID').value = exactMatches.length === 1 ? exactMatches[0].EmployeeID : '';
    }
});

function validateForm() { // 保留給既有呼叫端；產品改為多列，這裡只驗顧客與員工
    var customerID = document.getElementById('customerID').value;
    var employeeID = document.getElementById('EmployeeID').value;
    if (!customerID || !employeeID) {
        alert('Please select valid Customer ID and Employee ID');
        return false;
    }
    return true;
}
</script>
</body>
</html>
