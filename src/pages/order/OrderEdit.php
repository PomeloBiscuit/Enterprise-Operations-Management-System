<?php   // OrderEdit.php
require_once __DIR__ . '/../../auth.inc.php';
require_once __DIR__ . '/../../i18n.inc.php';
if (!can_view_business_data()) {
    echo "<p align='center'>" . t('common.permission_denied') . "</p>";
    exit;
}

$OrderID = $_POST['OrderID'] ?? $_GET['id'] ?? '';
$resultsPerPage = intval($_POST['resultsPerPage'] ?? $_GET['resultsPerPage'] ?? 5);
$row = false;
$existingItems = [];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    // 如果是 POST 請求
    try {
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
                throw new Exception(t('order.add.err_qty', ['pid' => $pid]));
            }
            $lineItems[$pid] = ($lineItems[$pid] ?? 0) + $qty;
        }
        if (count($lineItems) === 0) {
            throw new Exception(t('order.add.err_no_items'));
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
        $errorMessage = user_safe_error($e);
    }
}

$stmt = $pdo->prepare("SELECT * FROM Orders WHERE OrderID = :OrderID");
$stmt->execute([':OrderID' => $OrderID]);
$row = $stmt->fetch();

if ($row !== false) {
    $containStmt = $pdo->prepare("SELECT ProductID, Quantity FROM Contain WHERE OrderID = :OrderID ORDER BY ProductID");
    $containStmt->execute([':OrderID' => $OrderID]);
    $existingItems = $containStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch customers, products, and employees for selection
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);

// 產品下拉選項 HTML（可指定要選中的 ProductID），初始列與 JS 動態列共用
function renderProductOptions($products, $selectedId = null) {
    $html = '<option value="">' . t('order.form.select_product') . '</option>';
    foreach ($products as $product) {
        $selected = ((string) $product['ProductID'] === (string) $selectedId) ? ' selected' : '';
        $html .= '<option value="' . (int) $product['ProductID'] . '"' . $selected . '>'
            . htmlspecialchars($product['ProductID'] . ' - ' . $product['ProductName'], ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    return $html;
}
$blankProductOptions = renderProductOptions($products);
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;"><?php echo t('order.edit.title'); ?></h3>
    <hr>
    <?php if ($errorMessage !== ''): ?>
        <p><?php echo $errorMessage; ?></p>
    <?php endif; ?>
    <?php if ($row === false): ?>
        <p><?php echo t('common.record_not_found'); ?></p>
        <a href="index.php?Act=430&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
    <?php else: ?>
    <form method="POST" id="orderEditForm">
        <input type="hidden" name="OrderID" value="<?php echo htmlspecialchars((string) $row['OrderID'], ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="resultsPerPage" value="<?php echo $resultsPerPage; ?>">
        <div class="form-group">
            <label>Order ID</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string) $row['OrderID'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" id="employeeSearch" class="form-control" placeholder="<?php echo htmlspecialchars(t('order.edit.employee_search_ph')); ?>">
            <select name="EmployeeID" id="employeeID" class="form-control" required>
                <option value=""><?php echo t('order.form.select_employee'); ?></option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo htmlspecialchars((string) $employee['EmployeeID'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $employee['EmployeeID'] == $row['EmployeeID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['EmployeeID'] . ' - ' . $employee['EmployeeName'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Customer ID</label>
            <input type="text" id="customerSearch" class="form-control" placeholder="<?php echo htmlspecialchars(t('order.edit.customer_search_ph')); ?>">
            <select name="CustomerID" id="customerID" class="form-control" required>
                <option value=""><?php echo t('order.form.select_customer'); ?></option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars((string) $customer['CustomerID'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customer['CustomerID'] == $row['CustomerID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($customer['CustomerID'] . ' - ' . $customer['CustomerName'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label><?php echo t('order.field.line_items'); ?></label>
            <div class="table-responsive">
            <table class="table table-sm" style="width: 100%;">
                <thead>
                    <tr>
                        <th><?php echo t('order.field.product'); ?></th>
                        <th style="width: 120px;"><?php echo t('order.field.quantity'); ?></th>
                        <th style="width: 90px;"><?php echo t('common.action'); ?></th>
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
                                <button type="button" class="btn btn-danger btn-sm remove-line"><?php echo t('common.delete'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addLineItem"><?php echo t('order.add.add_row'); ?></button>
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
                    <button type="button" class="btn btn-danger btn-sm remove-line"><?php echo t('common.delete'); ?></button>
                </td>
            </tr>
        </template>

        <div class="form-group">
            <label>Order Time</label>
            <input type="datetime-local" name="OrderTime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($row['OrderTime'])); ?>" required>
        </div>
        <div class="form-group">
            <label>Ship Date</label>
            <input type="date" name="ShipDate" class="form-control" value="<?php echo htmlspecialchars((string) $row['ShipDate'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label>Tracking Number</label>
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo htmlspecialchars((string) $row['TrackingNumber'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="form-group">
            <label>Ship Method</label>
            <input type="text" id="shipMethodSearch" class="form-control" placeholder="<?php echo htmlspecialchars(t('order.edit.ship_method_search_ph')); ?>">
            <select name="ShipMethod" id="shipMethod" class="form-control">
                <option value="Air" <?php echo $row['ShipMethod'] == 'Air' ? 'selected' : ''; ?>>Air</option>
                <option value="Sea" <?php echo $row['ShipMethod'] == 'Sea' ? 'selected' : ''; ?>>Sea</option>
                <option value="Land" <?php echo $row['ShipMethod'] == 'Land' ? 'selected' : ''; ?>>Land</option>
            </select>
        </div>
        <br>
        <div style="text-align: center;">
<a href="index.php?Act=430&resultsPerPage=<?php echo $resultsPerPage; ?>" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="reset" class="btn btn-warning text-white"><?php echo t('common.clear'); ?></button>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="submit" class="btn btn-primary"><?php echo t('common.update'); ?></button>
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
            alert(<?php echo json_encode(t('order.js.keep_one_row')); ?>);
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
            alert(<?php echo json_encode(t('order.js.need_valid_line')); ?>);
        }
    });
});

document.getElementById('customerSearch').addEventListener('input', function() {
    var searchValue = this.value.toLowerCase();
    var options = <?php echo json_encode($customers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.CustomerName.toLowerCase().includes(searchValue) ||
            option.CustomerID.toString().includes(searchValue);
    });
    var customerSelect = document.getElementById('customerID');
    customerSelect.innerHTML = <?php echo json_encode('<option value="">' . t('order.form.select_customer') . '</option>'); ?>;
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
    var options = <?php echo json_encode($employees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var filteredOptions = options.filter(function(option) {
        return option.EmployeeName.toLowerCase().includes(searchValue) ||
            option.EmployeeID.toString().includes(searchValue);
    });
    var employeeSelect = document.getElementById('employeeID');
    employeeSelect.innerHTML = <?php echo json_encode('<option value="">' . t('order.form.select_employee') . '</option>'); ?>;
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
    var options = <?php echo json_encode($customers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
    var options = <?php echo json_encode($employees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
<?php endif; ?>
