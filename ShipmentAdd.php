<?php
function generateTrackingNumber() {
    return 'TN' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generateOrderID($pdo) {
    $stmt = $pdo->query("SELECT MAX(OrderID) AS maxOrderID FROM Orders");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['maxOrderID'] + 1 : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert new order
        $orderID = generateOrderID($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO Orders (OrderID, OrderTime, CustomerID, ProductID, TrackingNumber, ShipMethod)
            VALUES (:OrderID, NOW(), :CustomerID, :ProductID, :TrackingNumber, :ShipMethod)
        ");
        $stmt->execute([
            ':OrderID' => $orderID,
            ':CustomerID' => $_POST['CustomerID'],
            ':ProductID' => $_POST['ProductID'],
            ':TrackingNumber' => $_POST['TrackingNumber'],
            ':ShipMethod' => $_POST['ShipMethod']
        ]);

        // Insert new shipment
        $stmt = $pdo->prepare("
            INSERT INTO Shipment (EmployeeID, OrderID, ShipDate, TrackingNumber, ShipMethod, status)
            VALUES (:EmployeeID, :OrderID, :ShipDate, :TrackingNumber, :ShipMethod, :status)
        ");
        $stmt->execute([
            ':EmployeeID' => $_POST['EmployeeID'],
            ':OrderID' => $orderID,
            ':ShipDate' => $_POST['ShipDate'],
            ':TrackingNumber' => $_POST['TrackingNumber'],
            ':ShipMethod' => $_POST['ShipMethod'],
            ':status' => isset($_POST['status']) ? 1 : 0
        ]);

        $pdo->commit();
        header("Location: index.php?Act=470");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
}

// Fetch employees and customers for selection
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT ProductID, ProductName FROM Product")->fetchAll(PDO::FETCH_ASSOC);
$trackingNumber = generateTrackingNumber();
$orderID = generateOrderID($pdo);
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;">新增出貨紀錄</h3>
    <hr>
    <form method="POST">
        <div class="form-group">
            <label>員工ID</label>
            <input type="text" id="employeeSearch" class="form-control" placeholder="搜尋員工ID或名稱">
            <select name="EmployeeID" id="employeeID" class="form-control" required>
                <option value="">選擇員工</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>顧客ID</label>
            <input type="text" id="customerSearch" class="form-control" placeholder="搜尋顧客ID或名稱">
            <select name="CustomerID" id="customerID" class="form-control" required>
                <option value="">選擇顧客</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['CustomerID']; ?>"><?php echo $customer['CustomerID'] . ' - ' . $customer['CustomerName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>產品ID</label>
            <input type="text" id="productSearch" class="form-control" placeholder="搜尋產品ID或名稱">
            <select name="ProductID" id="productID" class="form-control" required>
                <option value="">選擇產品</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['ProductID']; ?>"><?php echo $product['ProductID'] . ' - ' . $product['ProductName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>訂單ID</label>
            <input type="text" name="OrderID" class="form-control" value="<?php echo $orderID; ?>" readonly>
        </div>
        <div class="form-group">
            <label>出貨日期</label>
            <input type="date" name="ShipDate" class="form-control" required>
        </div>
        <div class="form-group">
            <label>追蹤編號</label>
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo $trackingNumber; ?>" readonly>
        </div>
        <div class="form-group">
            <label>運輸方式</label>
            <input type="text" id="shipMethodSearch" class="form-control" placeholder="搜尋運輸方式">
            <select name="ShipMethod" id="shipMethod" class="form-control" required>
                <option value="">選擇運輸方式</option>
                <option value="Land">Land</option>
                <option value="Sea">Sea</option>
                <option value="Air">Air</option>
            </select>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <input type="checkbox" name="status"> 完成
        </div>
        <br>
        <div style="text-align: center;">
            <a href="index.php?Act=470" class="btn btn-secondary">返回</a>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="reset" class="btn btn-warning text-white">清除</button>
            <span style='display: inline-block; width: 20px;'></span>
            <button type="submit" class="btn btn-primary">新增</button>
        </div>
    </form>
</div>

<script>
document.getElementById('employeeSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($employees); ?>;
    const filteredOptions = options.filter(option => 
        option.EmployeeName.toLowerCase().includes(searchValue) || 
        option.EmployeeID.toString().includes(searchValue)
    );
    const employeeSelect = document.getElementById('employeeID');
    employeeSelect.innerHTML = '<option value="">選擇員工</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.EmployeeID;
        opt.textContent = `${option.EmployeeID} - ${option.EmployeeName}`;
        employeeSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        employeeSelect.value = filteredOptions[0].EmployeeID;
    }
});

document.getElementById('customerSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($customers); ?>;
    const filteredOptions = options.filter(option => 
        option.CustomerName.toLowerCase().includes(searchValue) || 
        option.CustomerID.toString().includes(searchValue)
    );
    const customerSelect = document.getElementById('customerID');
    customerSelect.innerHTML = '<option value="">選擇顧客</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.CustomerID;
        opt.textContent = `${option.CustomerID} - ${option.CustomerName}`;
        customerSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        customerSelect.value = filteredOptions[0].CustomerID;
    }
});

document.getElementById('productSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($products); ?>;
    const filteredOptions = options.filter(option => 
        option.ProductName.toLowerCase().includes(searchValue) || 
        option.ProductID.toString().includes(searchValue)
    );
    const productSelect = document.getElementById('productID');
    productSelect.innerHTML = '<option value="">選擇產品</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.ProductID;
        opt.textContent = `${option.ProductID} - ${option.ProductName}`;
        productSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        productSelect.value = filteredOptions[0].ProductID;
    }
});

document.getElementById('shipMethodSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = ['Land', 'Sea', 'Air'];
    const filteredOptions = options.filter(option => 
        option.toLowerCase().includes(searchValue)
    );
    const shipMethodSelect = document.getElementById('shipMethod');
    shipMethodSelect.innerHTML = '<option value="">選擇運輸方式</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        shipMethodSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        shipMethodSelect.value = filteredOptions[0];
    }
});

document.getElementById('employeeSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($employees); ?>;
    const filteredOptions = options.filter(option => 
        option.EmployeeName.toLowerCase().includes(searchValue) || 
        option.EmployeeID.toString().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('employeeID').value = filteredOptions[0].EmployeeID;
    } else {
        const exactMatch = options.find(option => option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('employeeID').value = exactMatch.EmployeeID;
        } else {
            document.getElementById('employeeID').value = '';
        }
    }
});

document.getElementById('customerSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($customers); ?>;
    const filteredOptions = options.filter(option => 
        option.CustomerName.toLowerCase().includes(searchValue) || 
        option.CustomerID.toString().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('customerID').value = filteredOptions[0].CustomerID;
    } else {
        const exactMatch = options.find(option => option.CustomerID.toString() === searchValue || option.CustomerName.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('customerID').value = exactMatch.CustomerID;
        } else {
            document.getElementById('customerID').value = '';
        }
    }
});

document.getElementById('productSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($products); ?>;
    const filteredOptions = options.filter(option => 
        option.ProductName.toLowerCase().includes(searchValue) || 
        option.ProductID.toString().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('productID').value = filteredOptions[0].ProductID;
    } else {
        const exactMatch = options.find(option => option.ProductID.toString() === searchValue || option.ProductName.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('productID').value = exactMatch.ProductID;
        } else {
            document.getElementById('productID').value = '';
        }
    }
});

document.getElementById('shipMethodSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = ['Land', 'Sea', 'Air'];
    const filteredOptions = options.filter(option => 
        option.toLowerCase().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('shipMethod').value = filteredOptions[0];
    } else {
        const exactMatch = options.find(option => option.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('shipMethod').value = exactMatch;
        } else {
            document.getElementById('shipMethod').value = '';
        }
    }
});
</script>
