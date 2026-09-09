<?php   // OrderEdit.php
require_once __DIR__ . '/../../auth.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>權限不足!</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    // 如果是 POST 請求
    try {
        $OrderID = $_POST['OrderID'];
        $CustomerID = $_POST['CustomerID'];
        $EmployeeID = $_POST['EmployeeID'];
        $OrderTime = $_POST['OrderTime'];
        $ShipDate = $_POST['ShipDate'];
        $TrackingNumber = $_POST['TrackingNumber'];
        $ShipMethod = $_POST['ShipMethod'];

        // 訂單明細：每列一個產品 + 數量。重複產品合併數量。
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
                continue;
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
            $chk = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE ProductID = :ProductID");
            $chk->execute([':ProductID' => $pid]);
            if ($chk->fetchColumn() == 0) {
                throw new Exception("Invalid Product ID: $pid");
            }
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE Orders
            SET CustomerID = :CustomerID,
                EmployeeID = :EmployeeID,
                OrderTime = :OrderTime,
                ShipDate = :ShipDate,
                TrackingNumber = :TrackingNumber,
                ShipMethod = :ShipMethod
            WHERE OrderID = :OrderID
        ");
        $stmt->execute([
            ':CustomerID' => $CustomerID,
            ':EmployeeID' => $EmployeeID,
            ':OrderTime' => $OrderTime,
            ':ShipDate' => $ShipDate,
            ':TrackingNumber' => $TrackingNumber,
            ':ShipMethod' => $ShipMethod,
            ':OrderID' => $OrderID
        ]);

        // 明細整批重寫：先刪再插，避免逐列 diff 的複雜度
        $pdo->prepare("DELETE FROM Contain WHERE OrderID = :OrderID")->execute([':OrderID' => $OrderID]);
        $ins = $pdo->prepare("INSERT INTO Contain (OrderID, ProductID, Quantity) VALUES (:OrderID, :ProductID, :Quantity)");
        foreach ($lineItems as $pid => $qty) {
            $ins->execute([':OrderID' => $OrderID, ':ProductID' => $pid, ':Quantity' => $qty]);
        }
        $pdo->commit();

        $resultsPerPage = $_POST['resultsPerPage'] ?? 5;
        header("Location: index.php?Act=430&resultsPerPage=$resultsPerPage");
        exit();
    } catch (Throwable $e) { // 例外處理（含 PDOException）
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<p>錯誤：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {    // 如果是 GET 請求
    $stmt = $pdo->prepare("SELECT * FROM Orders WHERE OrderID = :OrderID");
    $stmt->execute([':OrderID' => $_GET['id']]);
    $row = $stmt->fetch();

    // 既有明細
    $containStmt = $pdo->prepare("SELECT ProductID, Quantity FROM Contain WHERE OrderID = :OrderID ORDER BY ProductID");
    $containStmt->execute([':OrderID' => $_GET['id']]);
    $existingItems = $containStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch customers, products, and employees for selection
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);

// 產品下拉選項 HTML（可指定要選中的 ProductID），初始列與 JS 動態列共用
function renderProductOptions($products, $selectedId = null) {
    $html = '<option value="">選擇Product</option>';
    foreach ($products as $product) {
        $selected = ((string) $product['ProductID'] === (string) $selectedId) ? ' selected' : '';
        $html .= '<option value="' . (int) $product['ProductID'] . '"' . $selected . '>'
            . htmlspecialchars($product['ProductID'] . ' - ' . $product['ProductName'])
            . '</option>';
    }
    return $html;
}
$blankProductOptions = renderProductOptions($products);
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;">編輯訂單</h3>
    <hr>
    <form method="POST" id="orderEditForm">
        <input type="hidden" name="OrderID" value="<?php echo $row['OrderID']; ?>">
        <input type="hidden" name="resultsPerPage" value="<?php echo $_GET['resultsPerPage'] ?? 5; ?>">
        <div class="form-group">
            <label>Order ID</label>
            <input type="text" class="form-control" value="<?php echo $row['OrderID']; ?>" disabled>
        </div>
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" id="employeeSearch" class="form-control" placeholder="搜尋 Employee ID or Name">
            <select name="EmployeeID" id="employeeID" class="form-control" required>
                <option value="">選擇員工</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['EmployeeID']; ?>" <?php echo $employee['EmployeeID'] == $row['EmployeeID'] ? 'selected' : ''; ?>><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Customer ID</label>
            <input type="text" id="customerSearch" class="form-control" placeholder="搜尋 Customer ID or Name">
            <select name="CustomerID" id="customerID" class="form-control" required>
                <option value="">選擇顧客</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['CustomerID']; ?>" <?php echo $customer['CustomerID'] == $row['CustomerID'] ? 'selected' : ''; ?>><?php echo $customer['CustomerID'] . ' - ' . $customer['CustomerName']; ?></option>
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
                <tbody id="lineItemsBody">
                    <?php foreach ($existingItems as $item): ?>
                        <tr class="line-item">
                            <td>
                                <select name="ProductID[]" class="form-control line-product">
                                    <?php echo renderProductOptions($products, $item['ProductID']); ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="Quantity[]" class="form-control line-qty" min="1" step="1" value="<?php echo (int) $item['Quantity']; ?>">
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-line">刪除</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addLineItem">＋ 新增一列</button>
        </div>

        <template id="lineItemTemplate">
            <tr class="line-item">
                <td>
                    <select name="ProductID[]" class="form-control line-product">
                        <?php echo $blankProductOptions; ?>
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
            <input type="datetime-local" name="OrderTime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($row['OrderTime'])); ?>" required>
        </div>
        <div class="form-group">
            <label>Ship Date</label>
            <input type="date" name="ShipDate" class="form-control" value="<?php echo $row['ShipDate']; ?>">
        </div>
        <div class="form-group">
            <label>Tracking Number</label>
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo $row['TrackingNumber']; ?>" required>
        </div>
        <div class="form-group">
            <label>Ship Method</label>
            <input type="text" id="shipMethodSearch" class="form-control" placeholder="搜尋 Ship Method (Air, Sea, Land)">
            <select name="ShipMethod" id="shipMethod" class="form-control">
                <option value="Air" <?php echo $row['ShipMethod'] == 'Air' ? 'selected' : ''; ?>>Air</option>
                <option value="Sea" <?php echo $row['ShipMethod'] == 'Sea' ? 'selected' : ''; ?>>Sea</option>
                <option value="Land" <?php echo $row['ShipMethod'] == 'Land' ? 'selected' : ''; ?>>Land</option>
            </select>
        </div>
        <br>
        <div style="text-align: center;">
            <a href="index.php?Act=430&resultsPerPage=<?php echo $_GET['resultsPerPage'] ?? 5; ?>" class="btn btn-secondary">返回</a>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="reset" class="btn btn-warning text-white">清除</button>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="submit" class="btn btn-primary">更新</button>
        </div>
    </form>
</div>

<script src="js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // ---- 訂單明細：動態增減列 ----
    var lineItemTemplate = document.getElementById('lineItemTemplate');
    var lineItemsBody = document.getElementById('lineItemsBody');

    function addLineItem() {
        lineItemsBody.appendChild(lineItemTemplate.content.cloneNode(true));
    }
    if (lineItemsBody.querySelectorAll('.line-item').length === 0) {
        addLineItem(); // 沒有既有明細時至少給一列
    }

    $('#addLineItem').on('click', addLineItem);

    $(lineItemsBody).on('click', '.remove-line', function() {
        if (lineItemsBody.querySelectorAll('.line-item').length > 1) {
            $(this).closest('.line-item').remove();
        } else {
            alert('至少要保留一列明細');
        }
    });

    $('#orderEditForm').on('submit', function(e) {
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
});

document.getElementById('customerSearch').addEventListener('input', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($customers); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.CustomerName.toLowerCase().includes(searchValue) ||
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
    var employeeSelect = document.getElementById('employeeID');
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
            option.CustomerID.toString().includes(searchValue);
    });
    if (filteredOptions.length === 1) {
        document.getElementById('customerID').value = filteredOptions[0].CustomerID;
    } else {
        var exactMatch = options.find(function(option) {
            return option.CustomerName.toLowerCase() === searchValue || option.CustomerID.toString() === searchValue;
        });
        document.getElementById('customerID').value = exactMatch ? exactMatch.CustomerID : '';
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
        document.getElementById('employeeID').value = filteredOptions[0].EmployeeID;
    } else {
        var exactMatches = options.filter(function(option) {
            return option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue;
        });
        document.getElementById('employeeID').value = exactMatches.length === 1 ? exactMatches[0].EmployeeID : '';
    }
});

// Ship Method 搜尋與自動選擇
var shipMethods = ['Air', 'Sea', 'Land'];
document.getElementById('shipMethodSearch').addEventListener('input', function() {
    var searchValue = this.value.toLowerCase();
    var matchingMethods = shipMethods.filter(function(method) {
        return method.toLowerCase().includes(searchValue);
    });
    var shipSelect = document.getElementById('shipMethod');
    shipSelect.innerHTML = '';
    matchingMethods.forEach(function(method) {
        var option = document.createElement('option');
        option.value = method;
        option.textContent = method;
        shipSelect.appendChild(option);
    });
    if (matchingMethods.length === 1) {
        shipSelect.value = matchingMethods[0];
    }
});

document.getElementById('shipMethodSearch').addEventListener('blur', function() {
    var searchValue = this.value.toLowerCase();
    var matchingMethods = shipMethods.filter(function(method) {
        return method.toLowerCase().includes(searchValue);
    });
    if (matchingMethods.length === 1) {
        document.getElementById('shipMethod').value = matchingMethods[0];
    } else {
        var exactMatch = shipMethods.find(function(method) {
            return method.toLowerCase() === searchValue;
        });
        document.getElementById('shipMethod').value = exactMatch ? exactMatch : '';
    }
});
</script>
